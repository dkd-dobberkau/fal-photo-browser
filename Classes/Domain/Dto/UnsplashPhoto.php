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
