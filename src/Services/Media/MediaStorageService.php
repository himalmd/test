<?php

declare(strict_types=1);

namespace Snaply\Services\Media;

use Snaply\Models\Media;
use Snaply\Repositories\MediaRepository;

/**
 * Main media storage service that provides a facade for media operations.
 *
 * This service acts as the primary entry point for all media-related operations,
 * delegating to the appropriate storage backend based on configuration. It also
 * provides convenience methods for common operations.
 *
 * Usage:
 *   $service = MediaStorageService::create($config);
 *   $media = $service->store($uploadedFile);
 *   $url = $service->getUrl($media->getId());
 */
class MediaStorageService
{
    private MediaStorageInterface $storage;
    private MediaRepository $mediaRepository;

    public function __construct(
        MediaStorageInterface $storage,
        MediaRepository $mediaRepository
    ) {
        $this->storage = $storage;
        $this->mediaRepository = $mediaRepository;
    }

    /**
     * Create a MediaStorageService with the appropriate backend.
     *
     * @param array<string, mixed> $config Configuration array
     * @param MediaRepository|null $mediaRepository Optional repository instance
     * @return self
     * @throws MediaStorageException If configuration is invalid
     */
    public static function create(array $config, ?MediaRepository $mediaRepository = null): self
    {
        $mediaRepository ??= new MediaRepository();
        $storageType = $config['storage_type'] ?? 'local';

        $storage = match ($storageType) {
            'local' => self::createLocalStorage($config, $mediaRepository),
            's3' => throw MediaStorageException::configurationError('S3 storage not yet implemented'),
            'wordpress' => throw MediaStorageException::configurationError('WordPress storage not yet implemented'),
            default => throw MediaStorageException::configurationError(
                sprintf('Unknown storage type: %s', $storageType)
            ),
        };

        return new self($storage, $mediaRepository);
    }

    /**
     * Create a LocalStorageStrategy from configuration.
     */
    private static function createLocalStorage(
        array $config,
        MediaRepository $mediaRepository
    ): LocalStorageStrategy {
        $localConfig = $config['local'] ?? [];

        $basePath = $localConfig['base_path']
            ?? $config['base_path']
            ?? throw MediaStorageException::configurationError('Missing base_path configuration');

        $baseUrl = $localConfig['base_url']
            ?? $config['base_url']
            ?? '/uploads/media';

        $maxFileSize = $localConfig['max_file_size']
            ?? $config['max_file_size']
            ?? 10 * 1024 * 1024;

        $allowedMimeTypes = $localConfig['allowed_mime_types']
            ?? $config['allowed_mime_types']
            ?? null;

        $placeholderUrl = $localConfig['placeholder_url']
            ?? $config['placeholder_url']
            ?? '/assets/images/placeholder.png';

        return new LocalStorageStrategy(
            $mediaRepository,
            $basePath,
            $baseUrl,
            $maxFileSize,
            $allowedMimeTypes,
            $placeholderUrl
        );
    }

    /**
     * Store an uploaded file.
     *
     * @param UploadedFile  $file    The file to store
     * @param array<string, mixed> $options Additional storage options
     * @return Media The created media record
     * @throws MediaStorageException If storage fails
     */
    public function store(UploadedFile $file, array $options = []): Media
    {
        return $this->storage->store($file, $options);
    }

    /**
     * Store a file from a path.
     *
     * Convenience method for storing existing files.
     *
     * @param string      $filePath     Path to the file
     * @param string|null $originalName Original filename (optional)
     * @param array<string, mixed> $options Additional storage options
     * @return Media The created media record
     * @throws MediaStorageException If storage fails
     */
    public function storeFromPath(
        string $filePath,
        ?string $originalName = null,
        array $options = []
    ): Media {
        $file = UploadedFile::fromPath($filePath, $originalName);
        return $this->store($file, $options);
    }

    /**
     * Store a file from binary content.
     *
     * Convenience method for storing generated content (e.g., screenshots).
     *
     * @param string $content      Binary content
     * @param string $filename     Filename for the content
     * @param string $mimeType     MIME type
     * @param array<string, mixed> $options Additional storage options
     * @return Media The created media record
     * @throws MediaStorageException If storage fails
     */
    public function storeFromContent(
        string $content,
        string $filename,
        string $mimeType,
        array $options = []
    ): Media {
        $file = UploadedFile::fromContent($content, $filename, $mimeType);
        return $this->store($file, $options);
    }

    /**
     * Store a file from a PHP $_FILES array element.
     *
     * @param array{tmp_name: string, name: string, type: string, size: int, error: int} $fileData
     * @param array<string, mixed> $options Additional storage options
     * @return Media The created media record
     * @throws MediaStorageException If storage fails
     */
    public function storeFromUpload(array $fileData, array $options = []): Media
    {
        $file = UploadedFile::fromPhpUpload($fileData);
        return $this->store($file, $options);
    }

    /**
     * Get the URL for a media item.
     *
     * Returns null if the media doesn't exist. May return a placeholder
     * URL if the physical file is missing (graceful degradation).
     *
     * @param int $mediaId Media record ID
     * @return string|null Public URL or null
     */
    public function getUrl(int $mediaId): ?string
    {
        return $this->storage->getUrl($mediaId);
    }

    /**
     * Get URLs for multiple media items.
     *
     * @param int[] $mediaIds Array of media IDs
     * @return array<int, string|null> Map of media ID to URL
     */
    public function getUrls(array $mediaIds): array
    {
        $urls = [];
        foreach ($mediaIds as $mediaId) {
            $urls[$mediaId] = $this->getUrl($mediaId);
        }
        return $urls;
    }

    /**
     * Get the physical file path for a media item.
     *
     * May return null for remote storage backends.
     *
     * @param int $mediaId Media record ID
     * @return string|null File path or null
     */
    public function getPath(int $mediaId): ?string
    {
        return $this->storage->getPath($mediaId);
    }

    /**
     * Delete a media item.
     *
     * @param int $mediaId Media record ID
     * @return bool True if deletion was successful
     * @throws MediaStorageException If deletion fails
     */
    public function delete(int $mediaId): bool
    {
        return $this->storage->delete($mediaId);
    }

    /**
     * Check if a media item exists.
     *
     * @param int  $mediaId      Media record ID
     * @param bool $checkPhysical Also verify the physical file exists
     * @return bool True if exists
     */
    public function exists(int $mediaId, bool $checkPhysical = false): bool
    {
        return $this->storage->exists($mediaId, $checkPhysical);
    }

    /**
     * Get a media record by ID.
     *
     * @param int $mediaId Media record ID
     * @return Media|null Media record or null
     */
    public function getMedia(int $mediaId): ?Media
    {
        return $this->mediaRepository->findById($mediaId);
    }

    /**
     * Get the underlying storage implementation.
     *
     * @return MediaStorageInterface
     */
    public function getStorage(): MediaStorageInterface
    {
        return $this->storage;
    }

    /**
     * Get the storage type identifier.
     *
     * @return string Storage type (e.g., 'local', 's3', 'wordpress')
     */
    public function getStorageType(): string
    {
        return $this->storage->getStorageType();
    }

    /**
     * Find orphaned media records (not referenced by any snapshot).
     *
     * @return Media[] Array of orphaned media records
     */
    public function findOrphaned(): array
    {
        return $this->mediaRepository->findOrphaned();
    }

    /**
     * Clean up orphaned media (delete files not referenced by any snapshot).
     *
     * @param int $olderThanDays Only delete orphans older than this many days
     * @return int Number of media items deleted
     */
    public function cleanupOrphaned(int $olderThanDays = 7): int
    {
        $orphaned = $this->findOrphaned();
        $deleted = 0;
        $cutoffDate = new \DateTime("-{$olderThanDays} days");

        foreach ($orphaned as $media) {
            $createdAt = $media->getCreatedAt();
            if ($createdAt !== null && $createdAt < $cutoffDate) {
                try {
                    if ($this->delete($media->getId())) {
                        $deleted++;
                    }
                } catch (MediaStorageException $e) {
                    // Log error but continue cleanup
                    error_log(sprintf(
                        'Failed to delete orphaned media %d: %s',
                        $media->getId(),
                        $e->getMessage()
                    ));
                }
            }
        }

        return $deleted;
    }

    /**
     * Verify integrity of all media (check that physical files exist).
     *
     * @return array{valid: int, missing: array<int, string>} Integrity report
     */
    public function verifyIntegrity(): array
    {
        $allMedia = $this->mediaRepository->findAll();
        $valid = 0;
        $missing = [];

        foreach ($allMedia as $media) {
            if ($this->exists($media->getId(), true)) {
                $valid++;
            } else {
                $missing[$media->getId()] = $media->getStoragePath();
            }
        }

        return [
            'valid' => $valid,
            'missing' => $missing,
        ];
    }
}
