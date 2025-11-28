<?php

declare(strict_types=1);

namespace Snaply\Api\Handlers;

use Snaply\Api\ApiException;
use Snaply\Api\ApiResponse;
use Snaply\Services\SnapshotService;
use Snaply\Services\Media\UploadedFile;

/**
 * API handler for snapshot endpoints.
 *
 * Endpoints:
 * - GET    /api/pages/{pageId}/snapshots - List snapshots for page
 * - POST   /api/pages/{pageId}/snapshots - Create snapshot (multipart or JSON with base64)
 * - GET    /api/snapshots/{id}           - Get single snapshot (with optional ?include=comments)
 * - DELETE /api/snapshots/{id}           - Delete snapshot
 *
 * Creating snapshots:
 * - Accepts multipart/form-data with 'image' file and 'width', 'height' fields
 * - OR JSON with 'image' (base64), 'filename', 'width', 'height' fields
 */
class SnapshotHandler extends BaseHandler
{
    private SnapshotService $snapshotService;

    public function __construct(?SnapshotService $snapshotService = null)
    {
        $this->snapshotService = $snapshotService ?? new SnapshotService();
    }

    /**
     * Handle snapshot list request for a page.
     *
     * GET /api/pages/{pageId}/snapshots
     */
    public function listByPage(int $pageId): void
    {
        $snapshots = $this->snapshotService->listByPage($pageId);
        $this->success($snapshots);
    }

    /**
     * Handle create snapshot request.
     *
     * POST /api/pages/{pageId}/snapshots
     *
     * Multipart form data:
     *   - image: file (required) - Screenshot image
     *   - width: int (required) - Viewport width in pixels
     *   - height: int (required) - Viewport height in pixels
     *   - captured_at: string (optional) - ISO 8601 timestamp
     *
     * OR JSON body:
     *   - image: string (required) - Base64 encoded image (optionally with data URI prefix)
     *   - filename: string (optional) - Original filename (default: "screenshot.png")
     *   - width: int (required) - Viewport width in pixels
     *   - height: int (required) - Viewport height in pixels
     *   - captured_at: string (optional) - ISO 8601 timestamp
     */
    public function create(int $pageId): void
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'multipart/form-data')) {
            $this->createFromMultipart($pageId);
        } else {
            $this->createFromJson($pageId);
        }
    }

    /**
     * Create snapshot from multipart form data.
     */
    private function createFromMultipart(int $pageId): void
    {
        $multipart = $this->parseMultipartData();

        if (!isset($multipart['files']['image'])) {
            throw ApiException::validationError('Validation failed', [
                'image' => 'Image file is required',
            ]);
        }

        $fileInfo = $multipart['files']['image'];

        if ($fileInfo['error'] !== UPLOAD_ERR_OK) {
            throw ApiException::validationError('Validation failed', [
                'image' => $this->getUploadErrorMessage($fileInfo['error']),
            ]);
        }

        $uploadedFile = new UploadedFile(
            $fileInfo['tmp_name'],
            $fileInfo['name'],
            $fileInfo['type'],
            $fileInfo['size']
        );

        $data = [
            'width' => $multipart['data']['width'] ?? null,
            'height' => $multipart['data']['height'] ?? null,
            'captured_at' => $multipart['data']['captured_at'] ?? null,
        ];

        $snapshot = $this->snapshotService->create($pageId, $uploadedFile, $data);
        $this->created($snapshot);
    }

    /**
     * Create snapshot from JSON body with base64 image.
     */
    private function createFromJson(int $pageId): void
    {
        $data = $this->getJsonBody();

        if (!isset($data['image'])) {
            throw ApiException::validationError('Validation failed', [
                'image' => 'Image (base64) is required',
            ]);
        }

        $imageData = $this->parseBase64Image($data['image']);
        $filename = $data['filename'] ?? 'screenshot.png';

        $snapshotData = [
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'captured_at' => $data['captured_at'] ?? null,
        ];

        $snapshot = $this->snapshotService->createFromImageContent(
            $pageId,
            $imageData['content'],
            $filename,
            $imageData['mime_type'],
            $snapshotData
        );

        $this->created($snapshot);
    }

    /**
     * Handle get single snapshot request.
     *
     * GET /api/snapshots/{id}
     * Query params:
     *   - include: string - Comma-separated resources to include (e.g., "comments")
     */
    public function get(int $id): void
    {
        $includes = $this->getIncludes();

        if (in_array('comments', $includes, true)) {
            $result = $this->snapshotService->getWithComments($id);
            $this->success($result);
        } else {
            $snapshot = $this->snapshotService->get($id);
            $this->success($snapshot);
        }
    }

    /**
     * Handle delete snapshot request.
     *
     * DELETE /api/snapshots/{id}
     */
    public function delete(int $id): void
    {
        $this->snapshotService->delete($id);
        $this->noContent();
    }

    /**
     * Route a request to the appropriate method (page-scoped endpoints).
     *
     * @param string $method HTTP method
     * @param int $pageId Page ID
     */
    public function handlePageScoped(string $method, int $pageId): void
    {
        $method = strtoupper($method);

        match ($method) {
            'GET' => $this->listByPage($pageId),
            'POST' => $this->create($pageId),
            default => throw ApiException::methodNotAllowed($method, ['GET', 'POST']),
        };
    }

    /**
     * Route a request to the appropriate method (direct snapshot endpoints).
     *
     * @param string $method HTTP method
     * @param int $id Snapshot ID
     */
    public function handle(string $method, int $id): void
    {
        $method = strtoupper($method);

        match ($method) {
            'GET' => $this->get($id),
            'DELETE' => $this->delete($id),
            default => throw ApiException::methodNotAllowed($method, ['GET', 'DELETE']),
        };
    }

    /**
     * Get human-readable upload error message.
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension',
            default => 'Unknown upload error',
        };
    }
}
