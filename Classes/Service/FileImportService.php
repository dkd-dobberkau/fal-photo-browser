<?php

declare(strict_types=1);

namespace DkdDobberkau\FalPhotoBrowser\Service;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use DkdDobberkau\FalPhotoBrowser\Domain\Dto\UnsplashPhoto;

final class FileImportService
{
    public function __construct(
        private readonly UnsplashApiService $unsplashApiService,
        private readonly ResourceFactory $resourceFactory,
        private readonly StorageRepository $storageRepository,
    ) {}

    public function importPhoto(UnsplashPhoto $photo, int $storageUid = 1, string $targetFolder = 'unsplash'): File
    {
        $storage = $this->storageRepository->findByUid($storageUid);
        // The fallback storage has no permission evaluation, so it must stay unreachable.
        if ($storage === null || $storage->isFallbackStorage()) {
            throw new \InvalidArgumentException('Unknown file storage ' . $storageUid, 1793491201);
        }
        if (!UnsplashApiService::isValidId($photo->id)) {
            throw new \InvalidArgumentException('Invalid photo ID in API response', 1793491207);
        }

        // Build target path: unsplash/2026/01/photo-id.jpg
        $date = new \DateTimeImmutable();
        $relativePath = sprintf(
            '%s/%s/%s/%s.jpg',
            trim($targetFolder, '/'),
            $date->format('Y'),
            $date->format('m'),
            $photo->id
        );

        // Resolve the folder first, a refused import must not cost a download
        $folderPath = dirname($relativePath);

        if (!$storage->hasFolder($folderPath)) {
            $storage->createFolder($folderPath);
        }

        $folder = $storage->getFolder($folderPath);

        // Core exempts processing folders from file mount checks
        if ($storage->isWithinProcessingFolder($folder->getIdentifier())) {
            throw new \InvalidArgumentException('Target is a processing folder', 1793491208);
        }

        // Check if file already exists
        $fileName = basename($relativePath);
        if ($folder->hasFile($fileName)) {
            $existingFile = $this->resourceFactory->getFileObjectFromCombinedIdentifier(
                $storageUid . ':/' . $relativePath
            );
            if (!$existingFile->checkActionPermission('read')) {
                throw new \RuntimeException('Existing file is not readable', 1793491209);
            }
            return $existingFile;
        }

        // Trigger download tracking (required by Unsplash guidelines)
        $downloadUrl = $this->unsplashApiService->triggerDownload($photo->downloadUrl);

        $tempFile = GeneralUtility::tempnam('unsplash_');
        try {
            $this->unsplashApiService->downloadImage($downloadUrl, $tempFile);

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
                'copyright' => sprintf('Photo by %s on Unsplash', $photo->photographerName),
                'unsplash_photo_id' => $photo->id,
                'unsplash_photo_url' => $photo->unsplashUrl,
                'unsplash_photographer_name' => $photo->photographerName,
                'unsplash_photographer_url' => $photo->photographerUrl,
            ]);

            return $file;
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
