<?php

declare(strict_types=1);

namespace Snaply\Api\Handlers;

use Snaply\Api\ApiException;
use Snaply\Api\ApiResponse;

/**
 * Base handler providing common functionality for API handlers.
 */
abstract class BaseHandler
{
    /**
     * Get JSON request body.
     *
     * @return array Decoded JSON data
     * @throws ApiException If JSON is invalid
     */
    protected function getJsonBody(): array
    {
        $rawBody = file_get_contents('php://input');

        if ($rawBody === '' || $rawBody === false) {
            return [];
        }

        $data = json_decode($rawBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ApiException::badRequest('Invalid JSON: ' . json_last_error_msg());
        }

        return $data ?? [];
    }

    /**
     * Get query parameter.
     *
     * @param string $name Parameter name
     * @param mixed $default Default value if not present
     * @return mixed
     */
    protected function getQueryParam(string $name, mixed $default = null): mixed
    {
        return $_GET[$name] ?? $default;
    }

    /**
     * Check if deleted entities should be included.
     *
     * @return bool
     */
    protected function includeDeleted(): bool
    {
        $value = $this->getQueryParam('include_deleted', 'false');
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get include parameter for nested resources.
     *
     * @return array List of resources to include
     */
    protected function getIncludes(): array
    {
        $include = $this->getQueryParam('include', '');
        if ($include === '') {
            return [];
        }
        return array_map('trim', explode(',', $include));
    }

    /**
     * Parse multipart form data for file uploads.
     *
     * @return array{files: array, data: array}
     */
    protected function parseMultipartData(): array
    {
        return [
            'files' => $_FILES,
            'data' => $_POST,
        ];
    }

    /**
     * Require specific HTTP methods.
     *
     * @param string $actual Actual method
     * @param string[] $allowed Allowed methods
     * @throws ApiException If method not allowed
     */
    protected function requireMethod(string $actual, array $allowed): void
    {
        if (!in_array(strtoupper($actual), $allowed, true)) {
            throw ApiException::methodNotAllowed($actual, $allowed);
        }
    }

    /**
     * Parse a base64 encoded image from JSON body.
     *
     * @param string $base64Data Base64 encoded data (optionally with data URI prefix)
     * @return array{content: string, mime_type: string}
     * @throws ApiException If invalid
     */
    protected function parseBase64Image(string $base64Data): array
    {
        // Check for data URI format: data:image/png;base64,xxxxx
        if (preg_match('/^data:([a-z]+\/[a-z0-9.+-]+);base64,(.+)$/i', $base64Data, $matches)) {
            $mimeType = $matches[1];
            $content = base64_decode($matches[2], true);
        } else {
            // Plain base64
            $content = base64_decode($base64Data, true);
            $mimeType = 'image/png'; // Default
        }

        if ($content === false) {
            throw ApiException::badRequest('Invalid base64 image data');
        }

        // Validate it's an image by checking magic bytes
        $allowedMimeTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw ApiException::validationError('Invalid image type', [
                'image' => 'Allowed types: PNG, JPEG, GIF, WebP',
            ]);
        }

        return [
            'content' => $content,
            'mime_type' => $mimeType,
        ];
    }

    /**
     * Send a success response with data.
     */
    protected function success(mixed $data, int $statusCode = 200, array $meta = []): void
    {
        ApiResponse::success($data, $statusCode, $meta);
    }

    /**
     * Send a created response.
     */
    protected function created(mixed $data, ?string $location = null): void
    {
        ApiResponse::created($data, $location);
    }

    /**
     * Send a no content response.
     */
    protected function noContent(): void
    {
        ApiResponse::noContent();
    }
}
