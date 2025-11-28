<?php

declare(strict_types=1);

namespace Snaply\Services\Media;

use InvalidArgumentException;

/**
 * Value object representing an uploaded file.
 *
 * This class provides a consistent interface for handling file uploads,
 * whether from $_FILES, a file path, or binary content.
 */
class UploadedFile
{
    private string $tmpPath;
    private string $originalName;
    private string $mimeType;
    private int $size;
    private int $error;

    /**
     * PHP upload error code messages.
     */
    private const ERROR_MESSAGES = [
        UPLOAD_ERR_OK => 'File uploaded successfully',
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
    ];

    public function __construct(
        string $tmpPath,
        string $originalName,
        string $mimeType,
        int $size,
        int $error = UPLOAD_ERR_OK
    ) {
        $this->tmpPath = $tmpPath;
        $this->originalName = $originalName;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->error = $error;
    }

    /**
     * Create from PHP $_FILES array element.
     *
     * @param array{tmp_name: string, name: string, type: string, size: int, error: int} $fileData
     * @return self
     */
    public static function fromPhpUpload(array $fileData): self
    {
        return new self(
            $fileData['tmp_name'] ?? '',
            $fileData['name'] ?? '',
            $fileData['type'] ?? 'application/octet-stream',
            (int)($fileData['size'] ?? 0),
            (int)($fileData['error'] ?? UPLOAD_ERR_OK)
        );
    }

    /**
     * Create from an existing file path.
     *
     * @param string      $filePath     Path to the file
     * @param string|null $originalName Original filename (defaults to basename)
     * @param string|null $mimeType     MIME type (auto-detected if not provided)
     * @return self
     * @throws InvalidArgumentException If file doesn't exist
     */
    public static function fromPath(
        string $filePath,
        ?string $originalName = null,
        ?string $mimeType = null
    ): self {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        $originalName ??= basename($filePath);
        $mimeType ??= mime_content_type($filePath) ?: 'application/octet-stream';
        $size = filesize($filePath) ?: 0;

        return new self($filePath, $originalName, $mimeType, $size, UPLOAD_ERR_OK);
    }

    /**
     * Create from binary content.
     *
     * @param string $content      Binary content
     * @param string $originalName Original filename
     * @param string $mimeType     MIME type
     * @return self
     * @throws MediaStorageException If temp file creation fails
     */
    public static function fromContent(
        string $content,
        string $originalName,
        string $mimeType
    ): self {
        $tmpPath = tempnam(sys_get_temp_dir(), 'snaply_upload_');
        if ($tmpPath === false) {
            throw MediaStorageException::uploadFailed('Failed to create temporary file');
        }

        if (file_put_contents($tmpPath, $content) === false) {
            throw MediaStorageException::uploadFailed('Failed to write content to temporary file');
        }

        return new self($tmpPath, $originalName, $mimeType, strlen($content), UPLOAD_ERR_OK);
    }

    public function getTmpPath(): string
    {
        return $this->tmpPath;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    /**
     * Get the original filename without extension.
     */
    public function getBaseName(): string
    {
        return pathinfo($this->originalName, PATHINFO_FILENAME);
    }

    /**
     * Get the file extension from the original name.
     */
    public function getExtension(): string
    {
        return strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    /**
     * Get human-readable error message.
     */
    public function getErrorMessage(): string
    {
        return self::ERROR_MESSAGES[$this->error] ?? 'Unknown upload error';
    }

    /**
     * Check if upload was successful.
     */
    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && file_exists($this->tmpPath);
    }

    /**
     * Check if this is an image file based on MIME type.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mimeType, 'image/');
    }

    /**
     * Validate the file against constraints.
     *
     * @param int        $maxSize      Maximum file size in bytes
     * @param array|null $allowedMimes Allowed MIME types (null = all allowed)
     * @throws MediaStorageException If validation fails
     */
    public function validate(int $maxSize, ?array $allowedMimes = null): void
    {
        if (!$this->isValid()) {
            throw MediaStorageException::uploadFailed($this->getErrorMessage());
        }

        if ($this->size > $maxSize) {
            throw MediaStorageException::fileTooLarge($this->size, $maxSize);
        }

        if ($allowedMimes !== null && !in_array($this->mimeType, $allowedMimes, true)) {
            throw MediaStorageException::invalidFileType($this->mimeType, $allowedMimes);
        }
    }

    /**
     * Move the uploaded file to a new location.
     *
     * @param string $destination Destination path
     * @return bool True if move was successful
     */
    public function moveTo(string $destination): bool
    {
        // Use move_uploaded_file for actual PHP uploads, copy otherwise
        if (is_uploaded_file($this->tmpPath)) {
            return move_uploaded_file($this->tmpPath, $destination);
        }

        return copy($this->tmpPath, $destination);
    }

    /**
     * Get the file contents.
     */
    public function getContents(): string
    {
        if (!file_exists($this->tmpPath)) {
            return '';
        }

        return file_get_contents($this->tmpPath) ?: '';
    }
}
