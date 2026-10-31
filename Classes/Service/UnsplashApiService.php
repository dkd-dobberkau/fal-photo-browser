<?php

declare(strict_types=1);

namespace DkdDobberkau\FalPhotoBrowser\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use DkdDobberkau\FalPhotoBrowser\Domain\Dto\UnsplashPhoto;

final class UnsplashApiService
{
    private const API_BASE = 'https://api.unsplash.com/';
    private const ID_PATTERN = '/^[A-Za-z0-9_-]+\z/';
    private const IMAGE_URL_PATTERN = '#^https://([a-z0-9-]+\.)*unsplash\.com/#i';
    private const MAX_IMAGE_BYTES = 52428800;
    private const DOWNLOAD_TIMEOUT_SECONDS = 60;

    private HttpClientInterface $httpClient;
    private ?string $accessKey;

    public function __construct()
    {
        $this->accessKey = $_ENV['UNSPLASH_ACCESS_KEY'] ?? getenv('UNSPLASH_ACCESS_KEY') ?: null;
        // Absolute URLs on other hosts get no key. Scheme-relative URLs do, so response
        // supplied URLs must be checked before they reach this client.
        $this->httpClient = HttpClient::createForBaseUri(self::API_BASE, [
            'headers' => [
                'Accept-Version' => 'v1',
                'Authorization' => 'Client-ID ' . $this->accessKey,
            ],
        ]);
    }

    public function isConfigured(): bool
    {
        return $this->accessKey !== null && $this->accessKey !== '';
    }

    public static function isValidId(string $id): bool
    {
        return preg_match(self::ID_PATTERN, $id) === 1;
    }

    /**
     * @return array{photos: UnsplashPhoto[], total: int, totalPages: int}
     */
    public function search(
        string $query,
        int $page = 1,
        int $perPage = 30,
        ?string $orientation = null,
        ?string $color = null,
    ): array {
        $params = [
            'query' => $query,
            'page' => $page,
            'per_page' => $perPage,
        ];

        if ($orientation !== null) {
            $params['orientation'] = $orientation;
        }
        if ($color !== null) {
            $params['color'] = $color;
        }

        $response = $this->httpClient->request('GET', 'search/photos', [
            'query' => $params,
        ]);

        $data = $response->toArray();

        return [
            'photos' => array_map(
                fn(array $photo) => UnsplashPhoto::fromApiResponse($photo),
                $data['results']
            ),
            'total' => $data['total'],
            'totalPages' => $data['total_pages'],
        ];
    }

    /**
     * @return array{id: string, name: string, totalPhotos: int}[]
     */
    public function getCollections(int $page = 1, int $perPage = 30): array
    {
        $response = $this->httpClient->request('GET', 'collections', [
            'query' => [
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);

        return array_map(
            fn(array $collection) => [
                'id' => $collection['id'],
                'name' => $collection['title'],
                'totalPhotos' => $collection['total_photos'],
            ],
            $response->toArray()
        );
    }

    /**
     * @return UnsplashPhoto[]
     */
    public function getCollectionPhotos(string $collectionId, int $page = 1, int $perPage = 30): array
    {
        $this->assertValidId($collectionId);

        $response = $this->httpClient->request('GET', "collections/{$collectionId}/photos", [
            'query' => [
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);

        return array_map(
            fn(array $photo) => UnsplashPhoto::fromApiResponse($photo),
            $response->toArray()
        );
    }

    public function getPhoto(string $photoId): UnsplashPhoto
    {
        $this->assertValidId($photoId);

        $response = $this->httpClient->request('GET', "photos/{$photoId}");

        return UnsplashPhoto::fromApiResponse($response->toArray());
    }

    /**
     * Trigger download tracking (required by Unsplash API guidelines)
     */
    public function triggerDownload(string $downloadLocationUrl): string
    {
        if (!str_starts_with($downloadLocationUrl, self::API_BASE)) {
            throw new \InvalidArgumentException('Unexpected download location', 1793491202);
        }

        $response = $this->httpClient->request('GET', $downloadLocationUrl);

        $data = $response->toArray();
        return $data['url'];
    }

    /**
     * Streams the image to a file, the URL comes from an API response and is not trusted.
     */
    public function downloadImage(string $imageUrl, string $targetFile): void
    {
        if (preg_match(self::IMAGE_URL_PATTERN, $imageUrl) !== 1) {
            throw new \InvalidArgumentException('Unexpected image URL', 1793491203);
        }

        $response = $this->httpClient->request('GET', $imageUrl, [
            'max_redirects' => 0,
            'max_duration' => self::DOWNLOAD_TIMEOUT_SECONDS,
        ]);
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Image download failed', 1793491204);
        }

        $handle = fopen($targetFile, 'wb');
        $size = 0;
        try {
            foreach ($this->httpClient->stream($response) as $chunk) {
                $content = $chunk->getContent();
                $size += strlen($content);
                if ($size > self::MAX_IMAGE_BYTES) {
                    $response->cancel();
                    throw new \RuntimeException('Image exceeds the size limit', 1793491205);
                }
                fwrite($handle, $content);
            }
        } finally {
            fclose($handle);
        }

        if ($size === 0) {
            throw new \RuntimeException('Image download was empty', 1793491206);
        }
    }

    private function assertValidId(string $id): void
    {
        if (!self::isValidId($id)) {
            throw new \InvalidArgumentException('Invalid Unsplash ID', 1793491200);
        }
    }
}
