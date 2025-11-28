<?php

declare(strict_types=1);

namespace Snaply\Api\Handlers;

use Snaply\Api\ApiException;
use Snaply\Api\ApiResponse;
use Snaply\Services\PageService;

/**
 * API handler for page endpoints.
 *
 * Endpoints:
 * - GET    /api/projects/{projectId}/pages - List pages in project
 * - POST   /api/projects/{projectId}/pages - Create page in project
 * - GET    /api/pages/{id}                 - Get single page (with optional ?include=snapshots)
 * - PUT    /api/pages/{id}                 - Update page
 * - DELETE /api/pages/{id}                 - Soft delete page
 * - POST   /api/pages/{id}/restore         - Restore soft-deleted page
 */
class PageHandler extends BaseHandler
{
    private PageService $pageService;

    public function __construct(?PageService $pageService = null)
    {
        $this->pageService = $pageService ?? new PageService();
    }

    /**
     * Handle page list request for a project.
     *
     * GET /api/projects/{projectId}/pages
     * Query params:
     *   - include_deleted: bool - Include soft-deleted pages
     */
    public function listByProject(int $projectId): void
    {
        $includeDeleted = $this->includeDeleted();
        $pages = $this->pageService->listByProject($projectId, $includeDeleted);

        $this->success(ApiResponse::toArray($pages));
    }

    /**
     * Handle create page request.
     *
     * POST /api/projects/{projectId}/pages
     * Body (JSON):
     *   - url: string (required) - Page URL
     *   - title: string (optional) - Page title
     */
    public function create(int $projectId): void
    {
        $data = $this->getJsonBody();
        $page = $this->pageService->create($projectId, $data);

        $this->created($page->toArray());
    }

    /**
     * Handle get single page request.
     *
     * GET /api/pages/{id}
     * Query params:
     *   - include: string - Comma-separated resources to include (e.g., "snapshots")
     *   - include_deleted: bool - Include soft-deleted entities
     */
    public function get(int $id): void
    {
        $includes = $this->getIncludes();
        $includeDeleted = $this->includeDeleted();

        if (in_array('snapshots', $includes, true)) {
            $result = $this->pageService->getWithSnapshots($id, $includeDeleted);
            $this->success([
                'page' => $result['page']->toArray(),
                'snapshots' => ApiResponse::toArray($result['snapshots']),
            ]);
        } else {
            $page = $this->pageService->get($id, $includeDeleted);
            $this->success($page->toArray());
        }
    }

    /**
     * Handle update page request.
     *
     * PUT /api/pages/{id}
     * Body (JSON):
     *   - url: string (optional) - New page URL
     *   - title: string|null (optional) - New title
     */
    public function update(int $id): void
    {
        $data = $this->getJsonBody();
        $page = $this->pageService->update($id, $data);

        $this->success($page->toArray());
    }

    /**
     * Handle delete page request.
     *
     * DELETE /api/pages/{id}
     */
    public function delete(int $id): void
    {
        $this->pageService->delete($id);
        $this->noContent();
    }

    /**
     * Handle restore page request.
     *
     * POST /api/pages/{id}/restore
     */
    public function restore(int $id): void
    {
        $this->pageService->restore($id);
        $page = $this->pageService->get($id);

        $this->success($page->toArray());
    }

    /**
     * Route a request to the appropriate method (project-scoped endpoints).
     *
     * @param string $method HTTP method
     * @param int $projectId Project ID
     */
    public function handleProjectScoped(string $method, int $projectId): void
    {
        $method = strtoupper($method);

        match ($method) {
            'GET' => $this->listByProject($projectId),
            'POST' => $this->create($projectId),
            default => throw ApiException::methodNotAllowed($method, ['GET', 'POST']),
        };
    }

    /**
     * Route a request to the appropriate method (direct page endpoints).
     *
     * @param string $method HTTP method
     * @param int $id Page ID
     * @param string|null $action Additional action (e.g., "restore")
     */
    public function handle(string $method, int $id, ?string $action = null): void
    {
        $method = strtoupper($method);

        if ($action === null) {
            match ($method) {
                'GET' => $this->get($id),
                'PUT', 'PATCH' => $this->update($id),
                'DELETE' => $this->delete($id),
                default => throw ApiException::methodNotAllowed($method, ['GET', 'PUT', 'PATCH', 'DELETE']),
            };
            return;
        }

        if ($action === 'restore' && $method === 'POST') {
            $this->restore($id);
            return;
        }

        throw ApiException::notFound('Endpoint', "{$method} /api/pages/{$id}/{$action}");
    }
}
