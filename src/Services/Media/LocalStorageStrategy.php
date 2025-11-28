<?php

declare(strict_types=1);

namespace Snaply\Services\Media;

use Snaply\Models\Media;
use Snaply\Repositories\MediaRepository;

/**
 * Local filesystem storage strategy for media files.
 *
 * Stores files in a configurable directory structure organized by date.
 * This is the default storage backend for development and simple deployments.
 */
class LocalStorageStrategy implements MediaStorageInterface
{
    private MediaRepository $mediaRepository;
    private string $basePath;
    private string $baseUrl;
    private int $maxFileSize;
    /** @var string[] */
    private array $allowedMimeTypes;
    private string $placeholderUrl;

    /**
     * Default allowed MIME types for image uploads.
     */
    private const DEFAULT_ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/svg+xml',
    ];

    /**
     * Default maximum file size (10MB).
     */
    private const DEFAULT_MAX_FILE_SIZE = 10 * 1024 * 1024;

    /**
     * @param MediaRepository       $mediaRepository Repository for media records
     * @param string                $basePath        Base filesystem path for storage
     * @param string                $baseUrl         Base URL for serving files
     * @param int                   $maxFileSize     Maximum file size in bytes
     * @param array<string>|null    $allowedMimeTypes Allowed MIME types (null = defaults)
     * @param string                $placeholderUrl  URL for placeholder image
     */
    public function __construct(
        MediaRepository $mediaRepository,
        string $basePath,
        string $baseUrl,
        int $maxFileSize = self::DEFAULT_MAX_FILE_SIZE,
        ?array $allowedMimeTypes = null,
        string $placeholderUrl = '/assets/images/placeholder.png'
    ) {
        $this->mediaRepository = $mediaRepository;
        $this->basePath = rtrim($basePath, '/');
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->maxFileSize = $maxFileSize;
        $this->allowedMimeTypes = $allowedMimeTypes ?? self::DEFAULT_ALLOWED_MIME_TYPES;
        $this->placeholderUrl = $placeholderUrl;

        $this->ensureDirectoryExists($this->basePath);
    }

    /**
     * @inheritDoc
     */
    public function store(UploadedFile $file, array $options = []): Media
    {
        // Validate the file
        $file->validate($this->maxFileSize, $this->allowedMimeTypes);

        // Generate storage path
        $storagePath = $this->generateStoragePath($file);
        $fullPath = $this->basePath . '/' . $storagePath;

        // Ensure directory exists
        $directory = dirname($fullPath);
        $this->ensureDirectoryExists($directory);

        // Move the file
        if (!$file->moveTo($fullPath)) {
            throw MediaStorageException::uploadFailed(
                'Failed to move uploaded file to storage location',
                $fullPath
            );
        }

        // Set proper permissions
        chmod($fullPath, 0644);

        // Create media record
        $media = new Media(
            $storagePath,
            $file->getMimeType(),
            $file->getSize(),
            Media::STORAGE_LOCAL
        );
        $media->setOriginalFilename($file->getOriginalName());

        // Persist to database
        return $this->mediaRepository->create($media);
    }

    /**
     * @inheritDoc
     */
    public function getUrl(int $mediaId): ?string
    {
        $media = $this->mediaRepository->findById($mediaId);

        if ($media === null) {
            return null;
        }

        // Check if physical file exists
        $fullPath = $this->basePath . '/' . $media->getStoragePath();
        if (!file_exists($fullPath)) {
            // Log warning about missing file (in production, use proper logging)
            error_log(sprintf(
                'Warning: Physical file missing for media ID %d: %s',
                $mediaId,
                $fullPath
            ));

            // Return placeholder for graceful degradation
            return $this->placeholderUrl;
        }

        return $this->baseUrl . '/' . $media->getStoragePath();
    }

    /**
     * @inheritDoc
     */
    public function getPath(int $mediaId): ?string
    {
        $media = $this->mediaRepository->findById($mediaId);

        if ($media === null) {
            return null;
        }

        $fullPath = $this->basePath . '/' . $media->getStoragePath();

        if (!file_exists($fullPath)) {
            return null;
        }

        return $fullPath;
    }

    /**
     * @inheritDoc
     */
    public function delete(int $mediaId): bool
    {
        $media = $this->mediaRepository->findById($mediaId);

        if ($media === null) {
            return false;
        }

        // Delete physical file if it exists
        $fullPath = $this->basePath . '/' . $media->getStoragePath();
        if (file_exists($fullPath)) {
            if (!unlink($fullPath)) {
                throw MediaStorageException::deleteFailed(
                    'Failed to delete physical file',
                    $mediaId
                );
            }

            // Try to clean up empty directories
            $this->cleanupEmptyDirectories(dirname($fullPath));
        }

        // Delete database record
        return $this->mediaRepository->delete($mediaId);
    }

    /**
     * @inheritDoc
     */
    public function exists(int $mediaId, bool $checkPhysical = false): bool
    {
        $media = $this->mediaRepository->findById($mediaId);

        if ($media === null) {
            return false;
        }

        if ($checkPhysical) {
            $fullPath = $this->basePath . '/' . $media->getStoragePath();
            return file_exists($fullPath);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getStorageType(): string
    {
        return Media::STORAGE_LOCAL;
    }

    /**
     * Get configuration values.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return [
            'storage_type' => $this->getStorageType(),
            'base_path' => $this->basePath,
            'base_url' => $this->baseUrl,
            'max_file_size' => $this->maxFileSize,
            'allowed_mime_types' => $this->allowedMimeTypes,
            'placeholder_url' => $this->placeholderUrl,
        ];
    }

    /**
     * Generate a unique storage path for a file.
     *
     * Path format: YYYY/MM/DD/{unique_id}_{sanitized_name}.{ext}
     *
     * @param UploadedFile $file The uploaded file
     * @return string Relative storage path
     */
    private function generateStoragePath(UploadedFile $file): string
    {
        $date = date('Y/m/d');
        $uniqueId = bin2hex(random_bytes(8));
        $sanitizedName = $this->sanitizeFilename($file->getBaseName());
        $extension = $file->getExtension();

        if ($extension !== '') {
            return sprintf('%s/%s_%s.%s', $date, $uniqueId, $sanitizedName, $extension);
        }

        return sprintf('%s/%s_%s', $date, $uniqueId, $sanitizedName);
    }

    /**
     * Sanitize a filename for safe storage.
     *
     * @param string $filename Original filename
     * @return string Sanitized filename
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove any path components
        $filename = basename($filename);

        // Replace unsafe characters with underscores
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);

        // Collapse multiple underscores
        $filename = preg_replace('/_+/', '_', $filename);

        // Trim underscores from ends
        $filename = trim($filename, '_');

        // Limit length
        if (strlen($filename) > 50) {
            $filename = substr($filename, 0, 50);
        }

        // Ensure non-empty
        if ($filename === '') {
            $filename = 'file';
        }

        return strtolower($filename);
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     *
     * @param string $directory Directory path
     * @throws MediaStorageException If directory cannot be created
     */
    private function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw MediaStorageException::permissionDenied($directory);
        }
    }

    /**
     * Clean up empty directories after file deletion.
     *
     * Removes empty date-based directories up to the base path.
     *
     * @param string $directory Directory to check
     */
    private function cleanupEmptyDirectories(string $directory): void
    {
        // Don't delete beyond base path
        if ($directory === $this->basePath || !str_starts_with($directory, $this->basePath)) {
            return;
        }

        // Check if directory is empty
        $files = scandir($directory);
        if ($files === false) {
            return;
        }

        // Filter out . and ..
        $files = array_diff($files, ['.', '..']);

        if (empty($files)) {
            rmdir($directory);
            // Recursively check parent
            $this->cleanupEmptyDirectories(dirname($directory));
        }
    }
}
