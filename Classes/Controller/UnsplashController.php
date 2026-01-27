<?php

declare(strict_types=1);

namespace Vendor\T3Unsplash\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\JsonResponse;
use Vendor\T3Unsplash\Service\FileImportService;
use Vendor\T3Unsplash\Service\UnsplashApiService;

#[AsController]
final class UnsplashController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly UnsplashApiService $unsplashApiService,
        private readonly FileImportService $fileImportService,
    ) {}

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);

        $moduleTemplate->assignMultiple([
            'isConfigured' => $this->unsplashApiService->isConfigured(),
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
        $params = $request->getQueryParams();

        $query = $params['query'] ?? '';
        $page = (int)($params['page'] ?? 1);
        $orientation = $params['orientation'] ?? null;
        $color = $params['color'] ?? null;

        if ($query === '') {
            return new JsonResponse(['photos' => [], 'total' => 0, 'totalPages' => 0]);
        }

        $result = $this->unsplashApiService->search(
            query: $query,
            page: $page,
            perPage: 30,
            orientation: $orientation ?: null,
            color: $color ?: null,
        );

        return new JsonResponse([
            'photos' => array_map(fn($photo) => $photo->toArray(), $result['photos']),
            'total' => $result['total'],
            'totalPages' => $result['totalPages'],
            'page' => $page,
        ]);
    }

    public function importAction(ServerRequestInterface $request): ResponseInterface
    {
        $body = json_decode($request->getBody()->getContents(), true);
        $photoId = $body['photoId'] ?? '';
        $storageUid = (int)($body['storageUid'] ?? 1);
        $targetFolder = $body['targetFolder'] ?? 'unsplash';

        if ($photoId === '') {
            return new JsonResponse(['success' => false, 'error' => 'No photo ID provided'], 400);
        }

        try {
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
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function collectionsAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $page = (int)($params['page'] ?? 1);

        $collections = $this->unsplashApiService->getCollections($page);

        return new JsonResponse(['collections' => $collections]);
    }
}
