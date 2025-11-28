<?php

declare(strict_types=1);

namespace Snaply\Api;

/**
 * Standardised API response builder.
 *
 * Provides consistent JSON response format across all endpoints:
 * - Success: {"success": true, "data": {...}, "meta": {...}}
 * - Error: {"success": false, "error": {"code": "...", "message": "..."}}
 */
class ApiResponse
{
    /**
     * Send a success response with data.
     *
     * @param mixed $data Response data
     * @param int $statusCode HTTP status code
     * @param array $meta Optional metadata (pagination, etc.)
     */
    public static function success(mixed $data, int $statusCode = 200, array $meta = []): void
    {
        self::sendHeaders($statusCode);

        $response = [
            'success' => true,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        echo json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Send a success response for resource creation.
     *
     * @param mixed $data Created resource data
     * @param string|null $location Optional Location header for created resource
     */
    public static function created(mixed $data, ?string $location = null): void
    {
        if ($location !== null) {
            header('Location: ' . $location);
        }

        self::success($data, 201);
    }

    /**
     * Send a success response with no content.
     */
    public static function noContent(): void
    {
        self::sendHeaders(204);
    }

    /**
     * Send an error response.
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param string $errorCode Machine-readable error code
     * @param array $details Additional error details
     */
    public static function error(
        string $message,
        int $statusCode = 500,
        string $errorCode = 'INTERNAL_ERROR',
        array $details = []
    ): void {
        self::sendHeaders($statusCode);

        $error = [
            'code' => $errorCode,
            'message' => $message,
        ];

        if (!empty($details)) {
            $error['details'] = $details;
        }

        $response = [
            'success' => false,
            'error' => $error,
        ];

        echo json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Send an error response from an ApiException.
     */
    public static function fromException(ApiException $exception): void
    {
        self::error(
            $exception->getMessage(),
            $exception->getHttpStatusCode(),
            $exception->getErrorCode(),
            $exception->getDetails()
        );
    }

    /**
     * Send common HTTP headers.
     */
    private static function sendHeaders(int $statusCode): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }

    /**
     * Convert a model or array of models to array representation.
     *
     * @param object|array $modelOrModels Single model or array of models
     * @return array
     */
    public static function toArray(object|array $modelOrModels): array
    {
        if (is_array($modelOrModels)) {
            return array_map(
                fn($model) => method_exists($model, 'toArray') ? $model->toArray() : (array) $model,
                $modelOrModels
            );
        }

        return method_exists($modelOrModels, 'toArray') ? $modelOrModels->toArray() : (array) $modelOrModels;
    }
}
