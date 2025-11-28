<?php

declare(strict_types=1);

namespace Snaply\Services\Media;

use Snaply\Models\Media;

/**
 * Interface for media storage backends.
 *
 * This interface defines the contract that all storage implementations must follow,
 * enabling seamless switching between local filesystem, S3, WordPress Media Library,
 * or any other storage backend without changing application code.
 */
interface MediaStorageInterface
{
    /**
     * Store an uploaded file.
     *
     * @param UploadedFile  $file    The file to store
     * @param array<string, mixed> $options Additional options for storage
     * @return Media The created media record with storage details
     * @throws MediaStorageException If storage fails
     */
    public function store(UploadedFile $file, array $options = []): Media;

    /**
     * Get the public URL for a media item.
     *
     * Returns null if the media doesn't exist or the URL cannot be generated.
     * May return a placeholder URL if the physical file is missing but the
     * record exists (graceful degradation).
     *
     * @param int $mediaId Media record ID
     * @return string|null Public URL or null if not found
     */
    public function getUrl(int $mediaId): ?string;

    /**
     * Get the physical file path for a media item.
     *
     * This may return null for remote storage backends (like S3) where
     * there is no local file path.
     *
     * @param int $mediaId Media record ID
     * @return string|null File path or null if not applicable/found
     */
    public function getPath(int $mediaId): ?string;

    /**
     * Delete a media item and its associated file.
     *
     * @param int $mediaId Media record ID
     * @return bool True if deletion was successful
     * @throws MediaStorageException If deletion fails
     */
    public function delete(int $mediaId): bool;

    /**
     * Check if a media item exists.
     *
     * @param int  $mediaId      Media record ID
     * @param bool $checkPhysical Also verify the physical file exists
     * @return bool True if exists
     */
    public function exists(int $mediaId, bool $checkPhysical = false): bool;

    /**
     * Get the storage type identifier.
     *
     * @return string Storage type (e.g., 'local', 's3', 'wordpress')
     */
    public function getStorageType(): string;
}
