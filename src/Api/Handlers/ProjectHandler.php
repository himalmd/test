<?php

declare(strict_types=1);

namespace Snaply\Api\Handlers;

use Snaply\Api\ApiException;
use Snaply\Api\ApiResponse;
use Snaply\Services\ProjectService;

/**
 * API handler for project endpoints.
 *
 * Endpoints:
 * - GET    /api/projects         - List all projects
 * - GET    /api/projects/{id}    - Get single project (with optional ?include=pages)
 * - POST   /api/projects         - Create project
 * - PUT    /api/projects/{id}    - Update project
 * - DELETE /api/projects/{id}    - Soft delete project
 * - POST   /api/projects/{id}/restore - Restore soft-deleted project
 */
class ProjectHandler extends BaseHandler
{
    private ProjectService $projectService;

    public function __construct(?ProjectService $projectService = null)
    {
        $this->projectService = $projectService ?? new ProjectService();
    }

    /**
     * Handle project list request.
     *
     * GET /api/projects
     * Query params:
     *   - include_deleted: bool - Include soft-deleted projects (default: false)
     */
    public function list(): void
    {
        $includeDeleted = $this->includeDeleted();
        $projects = $this->projectService->list($includeDeleted);

        $this->success(ApiResponse::toArray($projects));
    }

    /**
     * Handle get single project request.
     *
     * GET /api/projects/{id}
     * Query params:
     *   - include: string - Comma-separated resources to include (e.g., "pages")
     *   - include_deleted: bool - Include soft-deleted entities
     */
    public function get(int $id): void
    {
        $includes = $this->getIncludes();
        $includeDeleted = $this->includeDeleted();

        if (in_array('pages', $includes, true)) {
            $result = $this->projectService->getWithPages($id, $includeDeleted);
            $this->success([
                'project' => $result['project']->toArray(),
                'pages' => ApiResponse::toArray($result['pages']),
            ]);
        } else {
            $project = $this->projectService->get($id, $includeDeleted);
            $this->success($project->toArray());
        }
    }

    /**
     * Handle create project request.
     *
     * POST /api/projects
     * Body (JSON):
     *   - name: string (required) - Project name
     *   - description: string (optional) - Project description
     */
    public function create(): void
    {
        $data = $this->getJsonBody();
        $project = $this->projectService->create($data);

        $this->created($project->toArray());
    }

    /**
     * Handle update project request.
     *
     * PUT /api/projects/{id}
     * Body (JSON):
     *   - name: string (optional) - New project name
     *   - description: string|null (optional) - New description
     */
    public function update(int $id): void
    {
        $data = $this->getJsonBody();
        $project = $this->projectService->update($id, $data);

        $this->success($project->toArray());
    }

    /**
     * Handle delete project request.
     *
     * DELETE /api/projects/{id}
     */
    public function delete(int $id): void
    {
        $this->projectService->delete($id);
        $this->noContent();
    }

    /**
     * Handle restore project request.
     *
     * POST /api/projects/{id}/restore
     */
    public function restore(int $id): void
    {
        $this->projectService->restore($id);
        $project = $this->projectService->get($id);

        $this->success($project->toArray());
    }

    /**
     * Route a request to the appropriate method.
     *
     * @param string $method HTTP method
     * @param int|null $id Resource ID (null for collection endpoints)
     * @param string|null $action Additional action (e.g., "restore")
     */
    public function handle(string $method, ?int $id = null, ?string $action = null): void
    {
        $method = strtoupper($method);

        // Collection endpoints
        if ($id === null) {
            match ($method) {
                'GET' => $this->list(),
                'POST' => $this->create(),
                default => throw ApiException::methodNotAllowed($method, ['GET', 'POST']),
            };
            return;
        }

        // Single resource endpoints
        if ($action === null) {
            match ($method) {
                'GET' => $this->get($id),
                'PUT', 'PATCH' => $this->update($id),
                'DELETE' => $this->delete($id),
                default => throw ApiException::methodNotAllowed($method, ['GET', 'PUT', 'PATCH', 'DELETE']),
            };
            return;
        }

        // Action endpoints
        if ($action === 'restore' && $method === 'POST') {
            $this->restore($id);
            return;
        }

        throw ApiException::notFound('Endpoint', "{$method} /api/projects/{$id}/{$action}");
    }
}
