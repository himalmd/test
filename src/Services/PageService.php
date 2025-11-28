<?php

declare(strict_types=1);

namespace Snaply\Services;

use Snaply\Api\ApiException;
use Snaply\Models\Page;
use Snaply\Repositories\PageRepository;
use Snaply\Repositories\ProjectRepository;
use Snaply\Repositories\SnapshotRepository;

/**
 * Service layer for page operations.
 *
 * Orchestrates page-related business logic and repository interactions.
 * Respects soft-delete semantics for parent projects.
 */
class PageService
{
    private PageRepository $pageRepository;
    private ProjectRepository $projectRepository;
    private SnapshotRepository $snapshotRepository;

    public function __construct(
        ?PageRepository $pageRepository = null,
        ?ProjectRepository $projectRepository = null,
        ?SnapshotRepository $snapshotRepository = null
    ) {
        $this->pageRepository = $pageRepository ?? new PageRepository();
        $this->projectRepository = $projectRepository ?? new ProjectRepository();
        $this->snapshotRepository = $snapshotRepository ?? new SnapshotRepository();
    }

    /**
     * Create a new page in a project.
     *
     * @param int $projectId Parent project ID
     * @param array $data Page data with 'url' (required), 'title' (optional)
     * @return Page Created page
     * @throws ApiException If validation fails or project not found
     */
    public function create(int $projectId, array $data): Page
    {
        // Verify project exists and is not soft-deleted
        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            throw ApiException::notFound('Project', $projectId);
        }

        $this->validatePageData($data, true);

        $page = new Page();
        $page->setProjectId($projectId);
        $page->setUrl($data['url']);

        if (isset($data['title'])) {
            $page->setTitle($data['title']);
        }

        return $this->pageRepository->save($page);
    }

    /**
     * Get a page by ID.
     *
     * @param int $id Page ID
     * @param bool $includeDeleted Include soft-deleted pages
     * @return Page
     * @throws ApiException If page not found
     */
    public function get(int $id, bool $includeDeleted = false): Page
    {
        $page = $this->pageRepository->findById($id, $includeDeleted);

        if ($page === null) {
            throw ApiException::notFound('Page', $id);
        }

        // Check if parent project is soft-deleted (unless we're including deleted)
        if (!$includeDeleted) {
            $project = $this->projectRepository->findById($page->getProjectId());
            if ($project === null) {
                throw ApiException::notFound('Page', $id);
            }
        }

        return $page;
    }

    /**
     * List pages for a project.
     *
     * @param int $projectId Project ID
     * @param bool $includeDeleted Include soft-deleted pages
     * @return Page[]
     * @throws ApiException If project not found
     */
    public function listByProject(int $projectId, bool $includeDeleted = false): array
    {
        // Verify project exists
        $project = $this->projectRepository->findById($projectId, $includeDeleted);
        if ($project === null) {
            throw ApiException::notFound('Project', $projectId);
        }

        return $this->pageRepository->findByProjectId($projectId, $includeDeleted);
    }

    /**
     * Update a page.
     *
     * @param int $id Page ID
     * @param array $data Update data
     * @return Page Updated page
     * @throws ApiException If page not found or validation fails
     */
    public function update(int $id, array $data): Page
    {
        $page = $this->get($id);
        $this->validatePageData($data, false);

        if (isset($data['url'])) {
            $page->setUrl($data['url']);
        }

        if (array_key_exists('title', $data)) {
            $page->setTitle($data['title']);
        }

        return $this->pageRepository->save($page);
    }

    /**
     * Soft delete a page.
     *
     * @param int $id Page ID
     * @return bool Success
     * @throws ApiException If page not found
     */
    public function delete(int $id): bool
    {
        $this->get($id); // Verify exists and parent not deleted
        return $this->pageRepository->softDelete($id);
    }

    /**
     * Restore a soft-deleted page.
     *
     * @param int $id Page ID
     * @return bool Success
     * @throws ApiException If page not found or parent project is deleted
     */
    public function restore(int $id): bool
    {
        // Find including deleted
        $page = $this->pageRepository->findById($id, true);

        if ($page === null) {
            throw ApiException::notFound('Page', $id);
        }

        if ($page->getDeletedAt() === null) {
            throw ApiException::badRequest('Page is not deleted');
        }

        // Verify parent project is not deleted
        $project = $this->projectRepository->findById($page->getProjectId());
        if ($project === null) {
            throw ApiException::badRequest('Cannot restore page: parent project is deleted');
        }

        return $this->pageRepository->restore($id);
    }

    /**
     * Get page with its snapshots.
     *
     * @param int $id Page ID
     * @param bool $includeDeleted Include soft-deleted entities
     * @return array Page data with 'snapshots' array
     * @throws ApiException If page not found
     */
    public function getWithSnapshots(int $id, bool $includeDeleted = false): array
    {
        $page = $this->get($id, $includeDeleted);
        $snapshots = $this->snapshotRepository->findByPageId($id);

        return [
            'page' => $page,
            'snapshots' => $snapshots,
        ];
    }

    /**
     * Validate page data.
     *
     * @param array $data Data to validate
     * @param bool $isCreate Whether this is for creation (requires url)
     * @throws ApiException If validation fails
     */
    private function validatePageData(array $data, bool $isCreate): void
    {
        $errors = [];

        if ($isCreate && !isset($data['url'])) {
            $errors['url'] = 'URL is required';
        }

        if (isset($data['url'])) {
            $url = trim($data['url']);
            if ($url === '') {
                $errors['url'] = 'URL cannot be empty';
            } elseif (strlen($url) > 2048) {
                $errors['url'] = 'URL cannot exceed 2048 characters';
            } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
                $errors['url'] = 'URL must be a valid URL';
            }
        }

        if (isset($data['title']) && strlen($data['title']) > 500) {
            $errors['title'] = 'Title cannot exceed 500 characters';
        }

        if (!empty($errors)) {
            throw ApiException::validationError('Validation failed', $errors);
        }
    }
}
