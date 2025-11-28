<?php

declare(strict_types=1);

namespace Snaply\Models;

use DateTime;
use InvalidArgumentException;

/**
 * Media entity representing stored files (screenshots, attachments).
 *
 * This entity provides storage abstraction - the actual file location
 * is determined by the storage_type and storage_path combination.
 */
class Media
{
    public const STORAGE_LOCAL = 'local';
    public const STORAGE_WORDPRESS = 'wordpress';
    public const STORAGE_S3 = 's3';

    public const VALID_STORAGE_TYPES = [
        self::STORAGE_LOCAL,
        self::STORAGE_WORDPRESS,
        self::STORAGE_S3,
    ];

    private ?int $id = null;
    private string $storageType = self::STORAGE_LOCAL;
    private string $storagePath;
    private ?string $originalFilename = null;
    private string $mimeType;
    private int $fileSize;
    private ?DateTime $createdAt = null;

    public function __construct(
        string $storagePath,
        string $mimeType,
        int $fileSize,
        string $storageType = self::STORAGE_LOCAL
    ) {
        $this->setStoragePath($storagePath);
        $this->setMimeType($mimeType);
        $this->setFileSize($fileSize);
        $this->setStorageType($storageType);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getStorageType(): string
    {
        return $this->storageType;
    }

    public function setStorageType(string $storageType): self
    {
        if (!in_array($storageType, self::VALID_STORAGE_TYPES, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Invalid storage type "%s". Must be one of: %s',
                    $storageType,
                    implode(', ', self::VALID_STORAGE_TYPES)
                )
            );
        }
        $this->storageType = $storageType;
        return $this;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function setStoragePath(string $storagePath): self
    {
        $storagePath = trim($storagePath);
        if ($storagePath === '') {
            throw new InvalidArgumentException('Storage path cannot be empty');
        }
        $this->storagePath = $storagePath;
        return $this;
    }

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(?string $originalFilename): self
    {
        $this->originalFilename = $originalFilename;
        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $mimeType = trim($mimeType);
        if ($mimeType === '') {
            throw new InvalidArgumentException('MIME type cannot be empty');
        }
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): self
    {
        if ($fileSize < 0) {
            throw new InvalidArgumentException('File size cannot be negative');
        }
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Check if this media is an image based on MIME type.
     *
     * @return bool
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    /**
     * Convert entity to associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'storage_type' => $this->storageType,
            'storage_path' => $this->storagePath,
            'original_filename' => $this->originalFilename,
            'mime_type' => $this->mimeType,
            'file_size' => $this->fileSize,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Create entity from database row.
     *
     * @param array<string, mixed> $data Database row
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $media = new self(
            $data['storage_path'],
            $data['mime_type'],
            (int)$data['file_size'],
            $data['storage_type'] ?? self::STORAGE_LOCAL
        );

        if (isset($data['id'])) {
            $media->setId((int)$data['id']);
        }

        if (isset($data['original_filename'])) {
            $media->setOriginalFilename($data['original_filename']);
        }

        if (!empty($data['created_at'])) {
            $media->setCreatedAt(new DateTime($data['created_at']));
        }

        return $media;
    }
}
