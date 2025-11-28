<?php

declare(strict_types=1);

namespace Snaply\Services;

use Snaply\Api\ApiException;
use Snaply\Models\Project;
use Snaply\Repositories\ProjectRepository;
use Snaply\Repositories\PageRepository;

/**
 * Service layer for project operations.
 *
 * Orchestrates project-related business logic and repository interactions.
 */
class ProjectService
{
    private ProjectRepository $projectRepository;
    private PageRepository $pageRepository;

    public function __construct(
        ?ProjectRepository $projectRepository = null,
        ?PageRepository $pageRepository = null
    ) {
        $this->projectRepository = $projectRepository ?? new ProjectRepository();
        $this->pageRepository = $pageRepository ?? new PageRepository();
    }

    /**
     * Create a new project.
     *
     * @param array $data Project data with 'name' (required), 'description' (optional)
     * @return Project Created project
     * @throws ApiException If validation fails
     */
    public function create(array $data): Project
    {
        $this->validateProjectData($data, true);

        $project = new Project(
            $data['name'],
            $data['description'] ?? null
        );

        return $this->projectRepository->create($project);
    }

    /**
     * Get a project by ID.
     *
     * @param int $id Project ID
     * @param bool $includeDeleted Include soft-deleted projects
     * @return Project
     * @throws ApiException If project not found
     */
    public function get(int $id, bool $includeDeleted = false): Project
    {
        $project = $this->projectRepository->findById($id, $includeDeleted);

        if ($project === null) {
            throw ApiException::notFound('Project', $id);
        }

        return $project;
    }

    /**
     * List all projects.
     *
     * @param bool $includeDeleted Include soft-deleted projects
     * @return Project[]
     */
    public function list(bool $includeDeleted = false): array
    {
        return $this->projectRepository->findAll($includeDeleted);
    }

    /**
     * Update a project.
     *
     * @param int $id Project ID
     * @param array $data Update data
     * @return Project Updated project
     * @throws ApiException If project not found or validation fails
     */
    public function update(int $id, array $data): Project
    {
        $project = $this->get($id);
        $this->validateProjectData($data, false);

        if (isset($data['name'])) {
            $project->setName($data['name']);
        }

        if (array_key_exists('description', $data)) {
            $project->setDescription($data['description']);
        }

        $this->projectRepository->update($project);

        return $this->projectRepository->findById($id, true);
    }

    /**
     * Soft delete a project.
     *
     * @param int $id Project ID
     * @return bool Success
     * @throws ApiException If project not found
     */
    public function delete(int $id): bool
    {
        $this->get($id); // Verify exists
        return $this->projectRepository->delete($id);
    }

    /**
     * Restore a soft-deleted project.
     *
     * @param int $id Project ID
     * @return bool Success
     * @throws ApiException If project not found
     */
    public function restore(int $id): bool
    {
        // Must include deleted to find it
        $project = $this->projectRepository->findById($id, true);

        if ($project === null) {
            throw ApiException::notFound('Project', $id);
        }

        if ($project->getDeletedAt() === null) {
            throw ApiException::badRequest('Project is not deleted');
        }

        return $this->projectRepository->restore($id);
    }

    /**
     * Get project with its pages.
     *
     * @param int $id Project ID
     * @param bool $includeDeleted Include soft-deleted entities
     * @return array Project data with 'pages' array
     * @throws ApiException If project not found
     */
    public function getWithPages(int $id, bool $includeDeleted = false): array
    {
        $project = $this->get($id, $includeDeleted);
        $pages = $this->pageRepository->findByProjectId($id, $includeDeleted);

        return [
            'project' => $project,
            'pages' => $pages,
        ];
    }

    /**
     * Validate project data.
     *
     * @param array $data Data to validate
     * @param bool $isCreate Whether this is for creation (requires name)
     * @throws ApiException If validation fails
     */
    private function validateProjectData(array $data, bool $isCreate): void
    {
        $errors = [];

        if ($isCreate && !isset($data['name'])) {
            $errors['name'] = 'Name is required';
        }

        if (isset($data['name'])) {
            $name = trim($data['name']);
            if ($name === '') {
                $errors['name'] = 'Name cannot be empty';
            } elseif (strlen($name) > 255) {
                $errors['name'] = 'Name cannot exceed 255 characters';
            }
        }

        if (isset($data['description']) && strlen($data['description']) > 65535) {
            $errors['description'] = 'Description is too long';
        }

        if (!empty($errors)) {
            throw ApiException::validationError('Validation failed', $errors);
        }
    }
}
