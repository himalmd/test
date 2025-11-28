<?php

declare(strict_types=1);

namespace Snaply\Api\Handlers;

use Snaply\Api\ApiException;
use Snaply\Api\ApiResponse;
use Snaply\Services\CommentService;

/**
 * API handler for comment endpoints.
 *
 * Endpoints:
 * - GET    /api/snapshots/{snapshotId}/comments - List comments for snapshot
 * - POST   /api/snapshots/{snapshotId}/comments - Create comment
 * - GET    /api/comments/{id}                   - Get single comment
 * - PUT    /api/comments/{id}                   - Update comment
 * - DELETE /api/comments/{id}                   - Delete comment
 *
 * Comments use normalised coordinates (0.0-1.0) for position pins.
 */
class CommentHandler extends BaseHandler
{
    private CommentService $commentService;

    public function __construct(?CommentService $commentService = null)
    {
        $this->commentService = $commentService ?? new CommentService();
    }

    /**
     * Handle comment list request for a snapshot.
     *
     * GET /api/snapshots/{snapshotId}/comments
     */
    public function listBySnapshot(int $snapshotId): void
    {
        $comments = $this->commentService->listBySnapshot($snapshotId);
        $this->success(ApiResponse::toArray($comments));
    }

    /**
     * Handle create comment request.
     *
     * POST /api/snapshots/{snapshotId}/comments
     * Body (JSON):
     *   - author_name: string (required) - Comment author's name
     *   - content: string (required) - Comment text
     *   - author_email: string (optional) - Author's email address
     *   - x_norm: float (optional) - Normalised X coordinate (0.0-1.0)
     *   - y_norm: float (optional) - Normalised Y coordinate (0.0-1.0)
     */
    public function create(int $snapshotId): void
    {
        $data = $this->getJsonBody();
        $comment = $this->commentService->create($snapshotId, $data);

        $this->created($comment->toArray());
    }

    /**
     * Handle get single comment request.
     *
     * GET /api/comments/{id}
     */
    public function get(int $id): void
    {
        $comment = $this->commentService->get($id);
        $this->success($comment->toArray());
    }

    /**
     * Handle update comment request.
     *
     * PUT /api/comments/{id}
     * Body (JSON):
     *   - author_name: string (optional) - New author name
     *   - content: string (optional) - New comment text
     *   - author_email: string|null (optional) - New email
     *   - x_norm: float|null (optional) - New X coordinate
     *   - y_norm: float|null (optional) - New Y coordinate
     */
    public function update(int $id): void
    {
        $data = $this->getJsonBody();
        $comment = $this->commentService->update($id, $data);

        $this->success($comment->toArray());
    }

    /**
     * Handle delete comment request.
     *
     * DELETE /api/comments/{id}
     */
    public function delete(int $id): void
    {
        $this->commentService->delete($id);
        $this->noContent();
    }

    /**
     * Route a request to the appropriate method (snapshot-scoped endpoints).
     *
     * @param string $method HTTP method
     * @param int $snapshotId Snapshot ID
     */
    public function handleSnapshotScoped(string $method, int $snapshotId): void
    {
        $method = strtoupper($method);

        match ($method) {
            'GET' => $this->listBySnapshot($snapshotId),
            'POST' => $this->create($snapshotId),
            default => throw ApiException::methodNotAllowed($method, ['GET', 'POST']),
        };
    }

    /**
     * Route a request to the appropriate method (direct comment endpoints).
     *
     * @param string $method HTTP method
     * @param int $id Comment ID
     */
    public function handle(string $method, int $id): void
    {
        $method = strtoupper($method);

        match ($method) {
            'GET' => $this->get($id),
            'PUT', 'PATCH' => $this->update($id),
            'DELETE' => $this->delete($id),
            default => throw ApiException::methodNotAllowed($method, ['GET', 'PUT', 'PATCH', 'DELETE']),
        };
    }
}
