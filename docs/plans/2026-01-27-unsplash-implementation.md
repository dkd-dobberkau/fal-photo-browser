# TYPO3 Unsplash Extension – Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Standalone TYPO3 v14 Extension für Unsplash-Integration im Backend

**Architecture:** Service-basierte Extension mit UnsplashApiService als Kern, Backend-Modul für UI, AJAX-Endpunkte für Suche/Import, FAL-Integration für Dateiverwaltung

**Tech Stack:** PHP 8.2+, TYPO3 v14, Symfony HttpClient, Vanilla JS mit TYPO3 Backend APIs

---

## Task 1: Extension-Grundgerüst

**Files:**
- Create: `composer.json`
- Create: `ext_emconf.php`
- Create: `Configuration/Services.yaml`

**Step 1: Create composer.json**

```json
{
    "name": "vendor/t3-unsplash",
    "type": "typo3-cms-extension",
    "description": "Unsplash image integration for TYPO3 backend",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": "^8.2",
        "typo3/cms-core": "^14.0",
        "typo3/cms-backend": "^14.0",
        "symfony/http-client": "^7.0"
    },
    "autoload": {
        "psr-4": {
            "Vendor\\T3Unsplash\\": "Classes/"
        }
    },
    "extra": {
        "typo3/cms": {
            "extension-key": "t3_unsplash"
        }
    }
}
```

**Step 2: Create ext_emconf.php**

```php
<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Unsplash Integration',
    'description' => 'Search and import Unsplash images directly in TYPO3 backend',
    'category' => 'be',
    'author' => '',
    'author_email' => '',
    'state' => 'beta',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.0.0-14.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
```

**Step 3: Create Services.yaml**

```yaml
services:
  _defaults:
    autowire: true
    autoconfigure: true
    public: false

  Vendor\T3Unsplash\:
    resource: '../Classes/*'
```

**Step 4: Commit**

```bash
git add composer.json ext_emconf.php Configuration/Services.yaml
git commit -m "feat: add extension skeleton"
```

---

## Task 2: UnsplashPhoto DTO

**Files:**
- Create: `Classes/Domain/Dto/UnsplashPhoto.php`

**Step 1: Create DTO class**

```php
<?php

declare(strict_types=1);

namespace Vendor\T3Unsplash\Domain\Dto;

final class UnsplashPhoto
{
    public function __construct(
        public readonly string $id,
        public readonly string $description,
        public readonly string $altDescription,
        public readonly int $width,
        public readonly int $height,
        public readonly string $color,
        public readonly string $thumbUrl,
        public readonly string $smallUrl,
        public readonly string $regularUrl,
        public readonly string $fullUrl,
        public readonly string $downloadUrl,
        public readonly string $photographerName,
        public readonly string $photographerUrl,
        public readonly string $unsplashUrl,
        public readonly \DateTimeImmutable $createdAt,
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: $data['id'],
            description: $data['description'] ?? '',
            altDescription: $data['alt_description'] ?? '',
            width: $data['width'],
            height: $data['height'],
            color: $data['color'] ?? '#000000',
            thumbUrl: $data['urls']['thumb'],
            smallUrl: $data['urls']['small'],
            regularUrl: $data['urls']['regular'],
            fullUrl: $data['urls']['full'],
            downloadUrl: $data['links']['download_location'],
            photographerName: $data['user']['name'],
            photographerUrl: $data['user']['links']['html'],
            unsplashUrl: $data['links']['html'],
            createdAt: new \DateTimeImmutable($data['created_at']),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'altDescription' => $this->altDescription,
            'width' => $this->width,
            'height' => $this->height,
            'color' => $this->color,
            'thumbUrl' => $this->thumbUrl,
            'smallUrl' => $this->smallUrl,
            'regularUrl' => $this->regularUrl,
            'fullUrl' => $this->fullUrl,
            'photographerName' => $this->photographerName,
            'photographerUrl' => $this->photographerUrl,
            'unsplashUrl' => $this->unsplashUrl,
        ];
    }
}
```

**Step 2: Commit**

```bash
git add Classes/Domain/Dto/UnsplashPhoto.php
git commit -m "feat: add UnsplashPhoto DTO"
```

---

## Task 3: UnsplashApiService

**Files:**
- Create: `Classes/Service/UnsplashApiService.php`

**Step 1: Create API service**

```php
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
```

**Step 2: Commit**

```bash
git add Classes/Service/UnsplashApiService.php
git commit -m "feat: add UnsplashApiService for API communication"
```

---

## Task 4: FileImportService

**Files:**
- Create: `Classes/Service/FileImportService.php`

**Step 1: Create import service**

```php
<?php

declare(strict_types=1);

namespace Vendor\T3Unsplash\Service;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Vendor\T3Unsplash\Domain\Dto\UnsplashPhoto;

final class FileImportService
{
    public function __construct(
        private readonly UnsplashApiService $unsplashApiService,
        private readonly ResourceFactory $resourceFactory,
        private readonly StorageRepository $storageRepository,
    ) {}

    public function importPhoto(UnsplashPhoto $photo, int $storageUid = 1, string $targetFolder = 'unsplash'): File
    {
        // Trigger download tracking (required by Unsplash guidelines)
        $downloadUrl = $this->unsplashApiService->triggerDownload($photo->downloadUrl);

        // Build target path: unsplash/2026/01/photo-id.jpg
        $date = new \DateTimeImmutable();
        $relativePath = sprintf(
            '%s/%s/%s/%s.jpg',
            trim($targetFolder, '/'),
            $date->format('Y'),
            $date->format('m'),
            $photo->id
        );

        // Download image
        $tempFile = GeneralUtility::tempnam('unsplash_');
        $imageContent = file_get_contents($downloadUrl);
        file_put_contents($tempFile, $imageContent);

        // Get storage and create folder structure
        $storage = $this->storageRepository->findByUid($storageUid);
        $folderPath = dirname($relativePath);

        if (!$storage->hasFolder($folderPath)) {
            $storage->createFolder($folderPath);
        }

        $folder = $storage->getFolder($folderPath);

        // Check if file already exists
        $fileName = basename($relativePath);
        if ($folder->hasFile($fileName)) {
            unlink($tempFile);
            return $this->resourceFactory->getFileObjectFromCombinedIdentifier(
                $storageUid . ':/' . $relativePath
            );
        }

        // Add file to storage
        $file = $storage->addFile($tempFile, $folder, $fileName);

        // Update metadata
        $metaDataRepository = GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\Resource\Index\MetaDataRepository::class
        );

        $metaDataRepository->update($file->getUid(), [
            'title' => $photo->altDescription ?: $photo->description,
            'description' => $photo->description,
            'alternative' => $photo->altDescription,
            'creator' => $photo->photographerName,
            'creator_tool' => 'Unsplash',
            'source' => $photo->unsplashUrl,
            'copyright' => sprintf(
                'Photo by %s on Unsplash',
                $photo->photographerName
            ),
        ]);

        // Cleanup temp file
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }

        return $file;
    }
}
```

**Step 2: Commit**

```bash
git add Classes/Service/FileImportService.php
git commit -m "feat: add FileImportService for downloading and FAL integration"
```

---

## Task 5: Backend-Modul Controller

**Files:**
- Create: `Classes/Controller/UnsplashController.php`

**Step 1: Create controller**

```php
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
```

**Step 2: Commit**

```bash
git add Classes/Controller/UnsplashController.php
git commit -m "feat: add UnsplashController for backend module"
```

---

## Task 6: Backend-Modul Konfiguration

**Files:**
- Create: `Configuration/Backend/Modules.php`
- Create: `Configuration/Backend/AjaxRoutes.php`

**Step 1: Create Modules.php**

```php
<?php

use Vendor\T3Unsplash\Controller\UnsplashController;

return [
    't3unsplash' => [
        'parent' => 'file',
        'position' => ['after' => 'file_FilelistList'],
        'access' => 'user',
        'workspaces' => 'live',
        'iconIdentifier' => 'module-unsplash',
        'path' => '/module/file/unsplash',
        'labels' => 'LLL:EXT:t3_unsplash/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => UnsplashController::class . '::indexAction',
            ],
        ],
    ],
];
```

**Step 2: Create AjaxRoutes.php**

```php
<?php

use Vendor\T3Unsplash\Controller\UnsplashController;

return [
    'unsplash_search' => [
        'path' => '/unsplash/search',
        'target' => UnsplashController::class . '::searchAction',
    ],
    'unsplash_import' => [
        'path' => '/unsplash/import',
        'methods' => ['POST'],
        'target' => UnsplashController::class . '::importAction',
    ],
    'unsplash_collections' => [
        'path' => '/unsplash/collections',
        'target' => UnsplashController::class . '::collectionsAction',
    ],
];
```

**Step 3: Commit**

```bash
git add Configuration/Backend/Modules.php Configuration/Backend/AjaxRoutes.php
git commit -m "feat: register backend module and AJAX routes"
```

---

## Task 7: Icon und Language Files

**Files:**
- Create: `Resources/Public/Icons/module-unsplash.svg`
- Create: `Resources/Private/Language/locallang_mod.xlf`
- Create: `Configuration/Icons.php`

**Step 1: Create module icon (SVG)**

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
  <rect x="3" y="3" width="18" height="18" rx="2"/>
  <circle cx="8.5" cy="8.5" r="1.5"/>
  <path d="M21 15l-5-5L5 21"/>
</svg>
```

**Step 2: Create locallang_mod.xlf**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
    <file source-language="en" datatype="plaintext" original="messages" date="2026-01-27T12:00:00Z">
        <header/>
        <body>
            <trans-unit id="mlang_tabs_tab" resname="mlang_tabs_tab">
                <source>Unsplash</source>
            </trans-unit>
            <trans-unit id="mlang_labels_tabdescr" resname="mlang_labels_tabdescr">
                <source>Search and import images from Unsplash</source>
            </trans-unit>
            <trans-unit id="mlang_labels_tablabel" resname="mlang_labels_tablabel">
                <source>Unsplash</source>
            </trans-unit>
        </body>
    </file>
</xliff>
```

**Step 3: Create Icons.php**

```php
<?php

return [
    'module-unsplash' => [
        'provider' => \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
        'source' => 'EXT:t3_unsplash/Resources/Public/Icons/module-unsplash.svg',
    ],
];
```

**Step 4: Commit**

```bash
git add Resources/Public/Icons/module-unsplash.svg Resources/Private/Language/locallang_mod.xlf Configuration/Icons.php
git commit -m "feat: add module icon and language files"
```

---

## Task 8: Fluid Template

**Files:**
- Create: `Resources/Private/Templates/Backend/Index.html`

**Step 1: Create template**

```html
<html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers"
      xmlns:be="http://typo3.org/ns/TYPO3/CMS/Backend/ViewHelpers"
      data-namespace-typo3-fluid="true">

<f:layout name="Module" />

<f:section name="Content">
    <f:if condition="{isConfigured}">
        <f:then>
            <div class="unsplash-browser" id="unsplash-browser">
                <div class="unsplash-search-form">
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text"
                                       class="form-control"
                                       id="unsplash-query"
                                       placeholder="Search Unsplash..."
                                       autocomplete="off">
                                <button class="btn btn-primary" type="button" id="unsplash-search-btn">
                                    Search
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="unsplash-orientation">
                                <f:for each="{orientations}" as="label" key="value">
                                    <option value="{value}">{label}</option>
                                </f:for>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="unsplash-color">
                                <f:for each="{colors}" as="label" key="value">
                                    <option value="{value}">{label}</option>
                                </f:for>
                            </select>
                        </div>
                    </div>
                </div>

                <div id="unsplash-results" class="unsplash-results"></div>

                <div id="unsplash-pagination" class="unsplash-pagination mt-4" style="display: none;">
                    <button class="btn btn-default" id="unsplash-load-more">
                        Load more
                    </button>
                </div>

                <div id="unsplash-loading" class="unsplash-loading" style="display: none;">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </f:then>
        <f:else>
            <f:be.infobox title="Configuration Required" state="1">
                <p>Please set the <code>UNSPLASH_ACCESS_KEY</code> environment variable to use this module.</p>
                <p>You can get an API key at <a href="https://unsplash.com/developers" target="_blank">unsplash.com/developers</a>.</p>
            </f:be.infobox>
        </f:else>
    </f:if>
</f:section>

</html>
```

**Step 2: Commit**

```bash
git add Resources/Private/Templates/Backend/Index.html
git commit -m "feat: add backend module Fluid template"
```

---

## Task 9: JavaScript für Such-UI

**Files:**
- Create: `Resources/Public/JavaScript/unsplash-browser.js`
- Create: `Resources/Public/Css/backend.css`

**Step 1: Create JavaScript**

```javascript
class UnsplashBrowser {
    constructor() {
        this.currentPage = 1;
        this.totalPages = 0;
        this.currentQuery = '';
        this.init();
    }

    init() {
        this.queryInput = document.getElementById('unsplash-query');
        this.searchBtn = document.getElementById('unsplash-search-btn');
        this.orientationSelect = document.getElementById('unsplash-orientation');
        this.colorSelect = document.getElementById('unsplash-color');
        this.resultsContainer = document.getElementById('unsplash-results');
        this.loadMoreBtn = document.getElementById('unsplash-load-more');
        this.paginationContainer = document.getElementById('unsplash-pagination');
        this.loadingIndicator = document.getElementById('unsplash-loading');

        if (!this.queryInput) return;

        this.searchBtn.addEventListener('click', () => this.search());
        this.queryInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.search();
        });
        this.orientationSelect.addEventListener('change', () => this.search());
        this.colorSelect.addEventListener('change', () => this.search());
        this.loadMoreBtn.addEventListener('click', () => this.loadMore());

        // Debounced search
        let timeout;
        this.queryInput.addEventListener('input', () => {
            clearTimeout(timeout);
            timeout = setTimeout(() => this.search(), 500);
        });
    }

    async search() {
        const query = this.queryInput.value.trim();
        if (!query) return;

        this.currentQuery = query;
        this.currentPage = 1;
        this.resultsContainer.innerHTML = '';
        await this.fetchPhotos();
    }

    async loadMore() {
        this.currentPage++;
        await this.fetchPhotos(true);
    }

    async fetchPhotos(append = false) {
        this.loadingIndicator.style.display = 'block';
        this.paginationContainer.style.display = 'none';

        const params = new URLSearchParams({
            query: this.currentQuery,
            page: this.currentPage,
            orientation: this.orientationSelect.value,
            color: this.colorSelect.value,
        });

        try {
            const response = await fetch(TYPO3.settings.ajaxUrls.unsplash_search + '&' + params);
            const data = await response.json();

            this.totalPages = data.totalPages;

            if (!append) {
                this.resultsContainer.innerHTML = '';
            }

            this.renderPhotos(data.photos);

            if (this.currentPage < this.totalPages) {
                this.paginationContainer.style.display = 'block';
            }
        } catch (error) {
            console.error('Search failed:', error);
            top.TYPO3.Notification.error('Error', 'Failed to search Unsplash');
        } finally {
            this.loadingIndicator.style.display = 'none';
        }
    }

    renderPhotos(photos) {
        photos.forEach(photo => {
            const card = document.createElement('div');
            card.className = 'unsplash-photo-card';
            card.style.backgroundColor = photo.color;
            card.innerHTML = `
                <img src="${photo.thumbUrl}" alt="${photo.altDescription}" loading="lazy">
                <div class="unsplash-photo-overlay">
                    <span class="photographer">${photo.photographerName}</span>
                    <button class="btn btn-sm btn-primary import-btn" data-photo-id="${photo.id}">
                        Import
                    </button>
                </div>
            `;

            card.querySelector('.import-btn').addEventListener('click', (e) => {
                e.stopPropagation();
                this.importPhoto(photo.id);
            });

            this.resultsContainer.appendChild(card);
        });
    }

    async importPhoto(photoId) {
        top.TYPO3.Notification.info('Importing', 'Downloading image from Unsplash...');

        try {
            const response = await fetch(TYPO3.settings.ajaxUrls.unsplash_import, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    photoId: photoId,
                    storageUid: 1,
                    targetFolder: 'unsplash',
                }),
            });

            const data = await response.json();

            if (data.success) {
                top.TYPO3.Notification.success('Success', `Image imported: ${data.file.name}`);
            } else {
                top.TYPO3.Notification.error('Error', data.error);
            }
        } catch (error) {
            console.error('Import failed:', error);
            top.TYPO3.Notification.error('Error', 'Failed to import image');
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new UnsplashBrowser();
});
```

**Step 2: Create CSS**

```css
.unsplash-results {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 16px;
}

.unsplash-photo-card {
    position: relative;
    aspect-ratio: 1;
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.2s;
}

.unsplash-photo-card:hover {
    transform: scale(1.02);
}

.unsplash-photo-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.unsplash-photo-overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 12px;
    background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
    display: flex;
    justify-content: space-between;
    align-items: center;
    opacity: 0;
    transition: opacity 0.2s;
}

.unsplash-photo-card:hover .unsplash-photo-overlay {
    opacity: 1;
}

.unsplash-photo-overlay .photographer {
    color: white;
    font-size: 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 60%;
}

.unsplash-loading {
    display: flex;
    justify-content: center;
    padding: 40px;
}

.unsplash-pagination {
    display: flex;
    justify-content: center;
}
```

**Step 3: Commit**

```bash
git add Resources/Public/JavaScript/unsplash-browser.js Resources/Public/Css/backend.css
git commit -m "feat: add JavaScript and CSS for backend UI"
```

---

## Task 10: Asset Loading im Template

**Files:**
- Modify: `Resources/Private/Templates/Backend/Index.html`

**Step 1: Add asset loading to template**

Add nach `<f:layout name="Module" />`:

```html
<f:section name="Before">
    <f:asset.css identifier="unsplash-backend" href="EXT:t3_unsplash/Resources/Public/Css/backend.css" />
    <f:asset.script identifier="unsplash-browser" src="EXT:t3_unsplash/Resources/Public/JavaScript/unsplash-browser.js" />
</f:section>
```

**Step 2: Commit**

```bash
git add Resources/Private/Templates/Backend/Index.html
git commit -m "feat: load CSS and JS assets in backend module"
```

---

## Task 11: TCA für Custom Metadata Fields

**Files:**
- Create: `ext_tables.sql`
- Create: `Configuration/TCA/Overrides/sys_file_metadata.php`

**Step 1: Create ext_tables.sql**

```sql
CREATE TABLE sys_file_metadata (
    unsplash_photo_id varchar(50) DEFAULT '' NOT NULL,
    unsplash_photo_url varchar(500) DEFAULT '' NOT NULL,
    unsplash_photographer_url varchar(500) DEFAULT '' NOT NULL
);
```

**Step 2: Create TCA Override**

```php
<?php

defined('TYPO3') or die();

$columns = [
    'unsplash_photo_id' => [
        'label' => 'Unsplash Photo ID',
        'config' => [
            'type' => 'input',
            'readOnly' => true,
        ],
    ],
    'unsplash_photo_url' => [
        'label' => 'Unsplash Photo URL',
        'config' => [
            'type' => 'link',
            'readOnly' => true,
        ],
    ],
    'unsplash_photographer_url' => [
        'label' => 'Photographer URL',
        'config' => [
            'type' => 'link',
            'readOnly' => true,
        ],
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('sys_file_metadata', $columns);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes(
    'sys_file_metadata',
    '--div--;Unsplash,unsplash_photo_id,unsplash_photo_url,unsplash_photographer_url'
);
```

**Step 3: Update FileImportService to save Unsplash metadata**

In `Classes/Service/FileImportService.php`, erweitere den `update()` Aufruf:

```php
$metaDataRepository->update($file->getUid(), [
    'title' => $photo->altDescription ?: $photo->description,
    'description' => $photo->description,
    'alternative' => $photo->altDescription,
    'creator' => $photo->photographerName,
    'creator_tool' => 'Unsplash',
    'source' => $photo->unsplashUrl,
    'copyright' => sprintf('Photo by %s on Unsplash', $photo->photographerName),
    'unsplash_photo_id' => $photo->id,
    'unsplash_photo_url' => $photo->unsplashUrl,
    'unsplash_photographer_url' => $photo->photographerUrl,
]);
```

**Step 4: Commit**

```bash
git add ext_tables.sql Configuration/TCA/Overrides/sys_file_metadata.php Classes/Service/FileImportService.php
git commit -m "feat: add custom metadata fields for Unsplash attribution"
```

---

## Summary

Nach Abschluss aller Tasks hast du eine funktionsfähige Extension mit:

- Backend-Modul unter "Datei → Unsplash"
- Suche mit Filtern (Orientierung, Farbe)
- Import-Funktion mit FAL-Integration
- Automatische Metadaten-Übernahme
- Unsplash API-Compliance (Download-Tracking)

**Noch nicht implementiert (für spätere Iteration):**
- Modal-Integration im Bildauswahl-Dialog
- FAL-Driver für virtuelles Storage
- Collection-Browser
