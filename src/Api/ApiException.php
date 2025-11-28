<?php

declare(strict_types=1);

namespace Snaply\Api;

use Exception;
use Throwable;

/**
 * API-specific exception with HTTP status code and error code.
 *
 * Provides structured error information for API responses.
 */
class ApiException extends Exception
{
    // Common error codes
    public const CODE_VALIDATION_ERROR = 'VALIDATION_ERROR';
    public const CODE_NOT_FOUND = 'NOT_FOUND';
    public const CODE_CONFLICT = 'CONFLICT';
    public const CODE_FORBIDDEN = 'FORBIDDEN';
    public const CODE_UNAUTHORIZED = 'UNAUTHORIZED';
    public const CODE_METHOD_NOT_ALLOWED = 'METHOD_NOT_ALLOWED';
    public const CODE_INTERNAL_ERROR = 'INTERNAL_ERROR';
    public const CODE_BAD_REQUEST = 'BAD_REQUEST';
    public const CODE_UNPROCESSABLE_ENTITY = 'UNPROCESSABLE_ENTITY';

    private int $httpStatusCode;
    private string $errorCode;
    private array $details;

    /**
     * @param string $message Human-readable error message
     * @param int $httpStatusCode HTTP status code for the response
     * @param string $errorCode Machine-readable error code
     * @param array $details Additional error details (field errors, etc.)
     * @param Throwable|null $previous Previous exception for chaining
     */
    public function __construct(
        string $message,
        int $httpStatusCode = 500,
        string $errorCode = self::CODE_INTERNAL_ERROR,
        array $details = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->httpStatusCode = $httpStatusCode;
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    /**
     * Convert exception to array for JSON response.
     */
    public function toArray(): array
    {
        $result = [
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
        ];

        if (!empty($this->details)) {
            $result['details'] = $this->details;
        }

        return $result;
    }

    /**
     * Create a validation error exception.
     */
    public static function validationError(string $message, array $fieldErrors = []): self
    {
        return new self(
            $message,
            422,
            self::CODE_VALIDATION_ERROR,
            $fieldErrors
        );
    }

    /**
     * Create a not found exception.
     */
    public static function notFound(string $resource, int|string $identifier): self
    {
        return new self(
            sprintf('%s with identifier "%s" not found', $resource, $identifier),
            404,
            self::CODE_NOT_FOUND
        );
    }

    /**
     * Create a method not allowed exception.
     */
    public static function methodNotAllowed(string $method, array $allowedMethods): self
    {
        return new self(
            sprintf('Method %s not allowed. Allowed: %s', $method, implode(', ', $allowedMethods)),
            405,
            self::CODE_METHOD_NOT_ALLOWED,
            ['allowed_methods' => $allowedMethods]
        );
    }

    /**
     * Create a bad request exception.
     */
    public static function badRequest(string $message): self
    {
        return new self($message, 400, self::CODE_BAD_REQUEST);
    }

    /**
     * Create an internal error exception.
     */
    public static function internalError(string $message, ?Throwable $previous = null): self
    {
        return new self($message, 500, self::CODE_INTERNAL_ERROR, [], $previous);
    }
}
