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
