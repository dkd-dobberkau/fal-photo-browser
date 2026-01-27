<?php

declare(strict_types=1);

namespace Vendor\T3Unsplash\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Vendor\T3Unsplash\Domain\Dto\UnsplashPhoto;

final class UnsplashApiService
{
    private const API_BASE = 'https://api.unsplash.com/';

    private HttpClientInterface $httpClient;
    private ?string $accessKey;

    public function __construct()
    {
        $this->accessKey = $_ENV['UNSPLASH_ACCESS_KEY'] ?? getenv('UNSPLASH_ACCESS_KEY') ?: null;
        $this->httpClient = HttpClient::create([
            'base_uri' => self::API_BASE,
            'headers' => [
                'Accept-Version' => 'v1',
            ],
        ]);
    }

    public function isConfigured(): bool
    {
        return $this->accessKey !== null && $this->accessKey !== '';
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
            'client_id' => $this->accessKey,
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
                'client_id' => $this->accessKey,
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
        $response = $this->httpClient->request('GET', "collections/{$collectionId}/photos", [
            'query' => [
                'page' => $page,
                'per_page' => $perPage,
                'client_id' => $this->accessKey,
            ],
        ]);

        return array_map(
            fn(array $photo) => UnsplashPhoto::fromApiResponse($photo),
            $response->toArray()
        );
    }

    public function getPhoto(string $photoId): UnsplashPhoto
    {
        $response = $this->httpClient->request('GET', "photos/{$photoId}", [
            'query' => [
                'client_id' => $this->accessKey,
            ],
        ]);

        return UnsplashPhoto::fromApiResponse($response->toArray());
    }

    /**
     * Trigger download tracking (required by Unsplash API guidelines)
     */
    public function triggerDownload(string $downloadLocationUrl): string
    {
        $response = $this->httpClient->request('GET', $downloadLocationUrl, [
            'query' => [
                'client_id' => $this->accessKey,
            ],
        ]);

        $data = $response->toArray();
        return $data['url'];
    }
}
