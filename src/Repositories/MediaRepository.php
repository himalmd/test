<?php

declare(strict_types=1);

namespace Snaply\Repositories;

use Snaply\Models\Media;

/**
 * Repository for Media entity CRUD operations.
 *
 * Note: Media does not support soft delete - records are permanently deleted
 * when removed. Actual file cleanup is handled by the media storage service.
 */
class MediaRepository extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'media';
    }

    /**
     * Media does not support soft delete.
     */
    protected function supportsSoftDelete(): bool
    {
        return false;
    }

    /**
     * Find all media records.
     *
     * @return Media[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM media ORDER BY created_at DESC";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Media::fromArray($row), $rows);
    }

    /**
     * Find a media record by ID.
     *
     * @param int $id Media ID
     * @return Media|null
     */
    public function findById(int $id): ?Media
    {
        $sql = "SELECT * FROM media WHERE id = :id";

        $stmt = $this->executeQuery($sql, ['id' => $id]);
        $row = $stmt->fetch();

        return $row ? Media::fromArray($row) : null;
    }

    /**
     * Find media by storage type.
     *
     * @param string $storageType Storage type (local, wordpress, s3)
     * @return Media[]
     */
    public function findByStorageType(string $storageType): array
    {
        $sql = "SELECT * FROM media WHERE storage_type = :storage_type ORDER BY created_at DESC";

        $stmt = $this->executeQuery($sql, ['storage_type' => $storageType]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Media::fromArray($row), $rows);
    }

    /**
     * Find media by MIME type.
     *
     * @param string $mimeType MIME type (e.g., image/png)
     * @return Media[]
     */
    public function findByMimeType(string $mimeType): array
    {
        $sql = "SELECT * FROM media WHERE mime_type = :mime_type ORDER BY created_at DESC";

        $stmt = $this->executeQuery($sql, ['mime_type' => $mimeType]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Media::fromArray($row), $rows);
    }

    /**
     * Find all image media.
     *
     * @return Media[]
     */
    public function findImages(): array
    {
        $sql = "SELECT * FROM media WHERE mime_type LIKE 'image/%' ORDER BY created_at DESC";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Media::fromArray($row), $rows);
    }

    /**
     * Find media by storage path.
     *
     * @param string $storagePath Storage path
     * @return Media|null
     */
    public function findByStoragePath(string $storagePath): ?Media
    {
        $sql = "SELECT * FROM media WHERE storage_path = :storage_path";

        $stmt = $this->executeQuery($sql, ['storage_path' => $storagePath]);
        $row = $stmt->fetch();

        return $row ? Media::fromArray($row) : null;
    }

    /**
     * Create a new media record.
     *
     * @param Media $media Media entity to create
     * @return Media Created media with ID set
     */
    public function create(Media $media): Media
    {
        $sql = "INSERT INTO media (storage_type, storage_path, original_filename, mime_type, file_size, created_at)
                VALUES (:storage_type, :storage_path, :original_filename, :mime_type, :file_size, NOW())";

        $this->executeQuery($sql, [
            'storage_type' => $media->getStorageType(),
            'storage_path' => $media->getStoragePath(),
            'original_filename' => $media->getOriginalFilename(),
            'mime_type' => $media->getMimeType(),
            'file_size' => $media->getFileSize(),
        ]);

        $media->setId($this->lastInsertId());

        return $this->findById($media->getId());
    }

    /**
     * Update an existing media record.
     *
     * @param Media $media Media entity to update
     * @return bool True if update was successful
     */
    public function update(Media $media): bool
    {
        if ($media->getId() === null) {
            return false;
        }

        $sql = "UPDATE media
                SET storage_type = :storage_type,
                    storage_path = :storage_path,
                    original_filename = :original_filename,
                    mime_type = :mime_type,
                    file_size = :file_size
                WHERE id = :id";

        $stmt = $this->executeQuery($sql, [
            'id' => $media->getId(),
            'storage_type' => $media->getStorageType(),
            'storage_path' => $media->getStoragePath(),
            'original_filename' => $media->getOriginalFilename(),
            'mime_type' => $media->getMimeType(),
            'file_size' => $media->getFileSize(),
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a media record (permanent deletion).
     *
     * Note: This only removes the database record. The actual file
     * should be deleted by the media storage service before calling this.
     *
     * @param int $id Media ID
     * @return bool True if deletion was successful
     */
    public function delete(int $id): bool
    {
        return $this->hardDelete($id);
    }

    /**
     * Find orphaned media (not referenced by any snapshot).
     *
     * @return Media[]
     */
    public function findOrphaned(): array
    {
        $sql = "SELECT m.* FROM media m
                LEFT JOIN snapshots s ON m.id = s.media_id
                WHERE s.id IS NULL
                ORDER BY m.created_at ASC";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Media::fromArray($row), $rows);
    }

    /**
     * Get total storage size by type.
     *
     * @return array<string, int> Size in bytes by storage type
     */
    public function getTotalSizeByType(): array
    {
        $sql = "SELECT storage_type, SUM(file_size) as total_size
                FROM media
                GROUP BY storage_type";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll();

        $sizes = [];
        foreach ($rows as $row) {
            $sizes[$row['storage_type']] = (int)$row['total_size'];
        }

        return $sizes;
    }

    /**
     * Count media by storage type.
     *
     * @return array<string, int> Count by storage type
     */
    public function countByStorageType(): array
    {
        $sql = "SELECT storage_type, COUNT(*) as count
                FROM media
                GROUP BY storage_type";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['storage_type']] = (int)$row['count'];
        }

        return $counts;
    }
}
