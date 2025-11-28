<?php

declare(strict_types=1);

namespace Snaply\Services\Media;

use Exception;
use Throwable;

/**
 * Exception thrown when media storage operations fail.
 */
class MediaStorageException extends Exception
{
    public const CODE_FILE_NOT_FOUND = 1001;
    public const CODE_UPLOAD_FAILED = 1002;
    public const CODE_INVALID_FILE_TYPE = 1003;
    public const CODE_FILE_TOO_LARGE = 1004;
    public const CODE_STORAGE_FULL = 1005;
    public const CODE_PERMISSION_DENIED = 1006;
    public const CODE_DELETE_FAILED = 1007;
    public const CODE_INVALID_MEDIA_ID = 1008;
    public const CODE_CONFIGURATION_ERROR = 1009;

    private ?string $filePath = null;
    private ?int $mediaId = null;

    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        ?string $filePath = null,
        ?int $mediaId = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->filePath = $filePath;
        $this->mediaId = $mediaId;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function getMediaId(): ?int
    {
        return $this->mediaId;
    }

    /**
     * Create exception for file not found.
     */
    public static function fileNotFound(string $path, ?int $mediaId = null): self
    {
        return new self(
            sprintf('File not found: %s', $path),
            self::CODE_FILE_NOT_FOUND,
            null,
            $path,
            $mediaId
        );
    }

    /**
     * Create exception for upload failure.
     */
    public static function uploadFailed(string $reason, ?string $filePath = null): self
    {
        return new self(
            sprintf('File upload failed: %s', $reason),
            self::CODE_UPLOAD_FAILED,
            null,
            $filePath
        );
    }

    /**
     * Create exception for invalid file type.
     */
    public static function invalidFileType(string $mimeType, array $allowedTypes): self
    {
        return new self(
            sprintf(
                'Invalid file type "%s". Allowed types: %s',
                $mimeType,
                implode(', ', $allowedTypes)
            ),
            self::CODE_INVALID_FILE_TYPE
        );
    }

    /**
     * Create exception for file too large.
     */
    public static function fileTooLarge(int $fileSize, int $maxSize): self
    {
        return new self(
            sprintf(
                'File size %d bytes exceeds maximum allowed size of %d bytes',
                $fileSize,
                $maxSize
            ),
            self::CODE_FILE_TOO_LARGE
        );
    }

    /**
     * Create exception for storage full.
     */
    public static function storageFull(string $path): self
    {
        return new self(
            sprintf('Insufficient disk space in storage directory: %s', $path),
            self::CODE_STORAGE_FULL,
            null,
            $path
        );
    }

    /**
     * Create exception for permission denied.
     */
    public static function permissionDenied(string $path): self
    {
        return new self(
            sprintf('Permission denied for path: %s', $path),
            self::CODE_PERMISSION_DENIED,
            null,
            $path
        );
    }

    /**
     * Create exception for delete failure.
     */
    public static function deleteFailed(string $reason, ?int $mediaId = null): self
    {
        return new self(
            sprintf('Failed to delete media: %s', $reason),
            self::CODE_DELETE_FAILED,
            null,
            null,
            $mediaId
        );
    }

    /**
     * Create exception for invalid media ID.
     */
    public static function invalidMediaId(int $mediaId): self
    {
        return new self(
            sprintf('Media record not found for ID: %d', $mediaId),
            self::CODE_INVALID_MEDIA_ID,
            null,
            null,
            $mediaId
        );
    }

    /**
     * Create exception for configuration error.
     */
    public static function configurationError(string $message): self
    {
        return new self(
            sprintf('Storage configuration error: %s', $message),
            self::CODE_CONFIGURATION_ERROR
        );
    }
}
