<?php

declare(strict_types=1);

namespace DkdDobberkau\FalPhotoBrowser\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\JsonResponse;
use DkdDobberkau\FalPhotoBrowser\Service\AiChatService;
use DkdDobberkau\FalPhotoBrowser\Service\FileImportService;
use DkdDobberkau\FalPhotoBrowser\Service\UnsplashApiService;

#[AsController]
final class PhotoBrowserController
{
    private const MODULE_IDENTIFIER = 'falphotobrowser';
    private const TARGET_FOLDER_PATTERN = '#^[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*\z#';

    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly UnsplashApiService $unsplashApiService,
        private readonly FileImportService $fileImportService,
        private readonly AiChatService $aiChatService,
        private readonly ModuleProvider $moduleProvider,
        private readonly LoggerInterface $logger,
    ) {}

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);

        $moduleTemplate->assignMultiple([
            'isConfigured' => $this->unsplashApiService->isConfigured(),
            'isChatConfigured' => $this->isChatAvailable(),
            'orientations' => [
                '' => 'All',
                'landscape' => 'Landscape',
                'portrait' => 'Portrait',
                'squarish' => 'Square',
            ],
            'colors' => [
                '' => 'All Colors',
                'black_and_white' => 'Black & White',
                'black' => 'Black',
                'white' => 'White',
                'yellow' => 'Yellow',
                'orange' => 'Orange',
                'red' => 'Red',
                'purple' => 'Purple',
                'magenta' => 'Magenta',
                'green' => 'Green',
                'teal' => 'Teal',
                'blue' => 'Blue',
            ],
        ]);

        return $moduleTemplate->renderResponse('Backend/Index');
    }

    public function searchAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handleAjax('Photo search failed.', function () use ($request): ResponseInterface {
            $params = $request->getQueryParams();

            $query = $this->stringValue($params, 'query');
            $page = (int)($params['page'] ?? 1);

            if ($query === '') {
                return new JsonResponse(['photos' => [], 'total' => 0, 'totalPages' => 0]);
            }

            $result = $this->unsplashApiService->search(
                query: $query,
                page: $page,
                perPage: 30,
                orientation: $this->stringValue($params, 'orientation') ?: null,
                color: $this->stringValue($params, 'color') ?: null,
            );

            return new JsonResponse([
                'photos' => array_map(fn($photo) => $photo->toArray(), $result['photos']),
                'total' => $result['total'],
                'totalPages' => $result['totalPages'],
                'page' => $page,
            ]);
        });
    }

    public function importAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handleAjax('The photo could not be imported.', function () use ($request): ResponseInterface {
            $body = $this->jsonBody($request);
            $photoId = $this->stringValue($body, 'photoId');
            $storageUid = filter_var($body['storageUid'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $targetFolder = $this->stringValue($body, 'targetFolder', 'unsplash');

            if ($photoId === '') {
                return new JsonResponse(['success' => false, 'error' => 'No photo ID provided'], 400);
            }
            if (!UnsplashApiService::isValidId($photoId)) {
                return new JsonResponse(['success' => false, 'error' => 'Invalid photo ID'], 400);
            }
            if ($storageUid === false) {
                return new JsonResponse(['success' => false, 'error' => 'Invalid storage'], 400);
            }
            if (preg_match(self::TARGET_FOLDER_PATTERN, $targetFolder) !== 1) {
                return new JsonResponse(['success' => false, 'error' => 'Invalid target folder'], 400);
            }

            $photo = $this->unsplashApiService->getPhoto($photoId);
            $file = $this->fileImportService->importPhoto($photo, $storageUid, $targetFolder);

            return new JsonResponse([
                'success' => true,
                'file' => [
                    'uid' => $file->getUid(),
                    'name' => $file->getName(),
                    'path' => $file->getPublicUrl(),
                    'identifier' => $file->getCombinedIdentifier(),
                ],
            ]);
        });
    }

    public function chatAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handleAjax('The assistant request failed.', function () use ($request): ResponseInterface {
            $body = $this->jsonBody($request);
            $userMessage = $this->stringValue($body, 'message');
            $conversationHistory = is_array($body['history'] ?? null) ? $body['history'] : [];

            if ($userMessage === '') {
                return new JsonResponse(['success' => false, 'error' => 'No message provided'], 400);
            }

            if (!$this->aiChatService->isConfigured()) {
                return new JsonResponse(['success' => false, 'error' => 'AI chat not configured. Set up a provider in EXT:aim or set ANTHROPIC_API_KEY.'], 503);
            }

            $aiResponse = $this->aiChatService->chat($userMessage, $conversationHistory);

            $searchResults = null;
            if ($aiResponse['searchTerms'] !== '') {
                $result = $this->unsplashApiService->search(
                    query: $aiResponse['searchTerms'],
                    page: 1,
                    perPage: 30,
                    orientation: $aiResponse['orientation'],
                    color: $aiResponse['color'],
                );
                $searchResults = [
                    'photos' => array_map(fn($photo) => $photo->toArray(), $result['photos']),
                    'total' => $result['total'],
                    'totalPages' => $result['totalPages'],
                    'page' => 1,
                ];
            }

            return new JsonResponse([
                'success' => true,
                'chat' => $aiResponse,
                'search' => $searchResults,
            ]);
        });
    }

    public function collectionsAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->handleAjax('Loading collections failed.', function () use ($request): ResponseInterface {
            $params = $request->getQueryParams();
            $page = (int)($params['page'] ?? 1);

            $collections = $this->unsplashApiService->getCollections($page);

            return new JsonResponse(['collections' => $collections]);
        });
    }

    /**
     * Keeps photo search usable when the assistant backend cannot be determined.
     */
    private function isChatAvailable(): bool
    {
        try {
            return $this->aiChatService->isConfigured();
        } catch (\Throwable $e) {
            $this->logger->error('The assistant availability check failed.', ['exception' => $e]);
            return false;
        }
    }

    /**
     * AJAX routes only require a backend login, so the module access is enforced here.
     * Exception messages can carry upstream details and never reach the client.
     *
     * @param callable(): ResponseInterface $action
     */
    private function handleAjax(string $errorMessage, callable $action): ResponseInterface
    {
        if (!$this->moduleProvider->accessGranted(self::MODULE_IDENTIFIER, $GLOBALS['BE_USER'])) {
            return new JsonResponse(['success' => false, 'error' => 'Access denied'], 403);
        }

        try {
            return $action();
        } catch (\Throwable $e) {
            $this->logger->error($errorMessage, ['exception' => $e]);
            return new JsonResponse(['success' => false, 'error' => $errorMessage], 500);
        }
    }

    /**
     * @return array<mixed>
     */
    private function jsonBody(ServerRequestInterface $request): array
    {
        $body = json_decode($request->getBody()->getContents(), true);
        return is_array($body) ? $body : [];
    }

    /**
     * @param array<mixed> $data
     */
    private function stringValue(array $data, string $key, string $default = ''): string
    {
        $value = $data[$key] ?? $default;
        return is_string($value) ? $value : '';
    }
}
