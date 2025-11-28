<?php

declare(strict_types=1);

namespace Snaply\Repositories;

use Snaply\Models\Project;

/**
 * Repository for Project entity CRUD operations.
 */
class ProjectRepository extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'projects';
    }

    /**
     * Find all projects.
     *
     * @param bool $includeDeleted Whether to include soft-deleted projects
     * @return Project[]
     */
    public function findAll(bool $includeDeleted = false): array
    {
        $where = $this->buildWhereClause([], $includeDeleted);
        $sql = "SELECT * FROM projects {$where} ORDER BY created_at DESC";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Project::fromArray($row), $rows);
    }

    /**
     * Find a project by ID.
     *
     * @param int  $id             Project ID
     * @param bool $includeDeleted Whether to include soft-deleted projects
     * @return Project|null
     */
    public function findById(int $id, bool $includeDeleted = false): ?Project
    {
        $where = $this->buildWhereClause(['id' => $id], $includeDeleted);
        $sql = "SELECT * FROM projects {$where}";

        $stmt = $this->executeQuery($sql, ['id' => $id]);
        $row = $stmt->fetch();

        return $row ? Project::fromArray($row) : null;
    }

    /**
     * Find projects by status.
     *
     * @param string $status         Project status (active, archived, completed)
     * @param bool   $includeDeleted Whether to include soft-deleted projects
     * @return Project[]
     */
    public function findByStatus(string $status, bool $includeDeleted = false): array
    {
        $where = $this->buildWhereClause(['status' => $status], $includeDeleted);
        $sql = "SELECT * FROM projects {$where} ORDER BY created_at DESC";

        $stmt = $this->executeQuery($sql, ['status' => $status]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Project::fromArray($row), $rows);
    }

    /**
     * Find projects by name (partial match).
     *
     * @param string $name           Search term
     * @param bool   $includeDeleted Whether to include soft-deleted projects
     * @return Project[]
     */
    public function findByName(string $name, bool $includeDeleted = false): array
    {
        $softDeleteCond = $this->softDeleteCondition($includeDeleted);
        $where = "WHERE name LIKE :name";
        if ($softDeleteCond !== '') {
            $where .= " AND {$softDeleteCond}";
        }

        $sql = "SELECT * FROM projects {$where} ORDER BY created_at DESC";

        $stmt = $this->executeQuery($sql, ['name' => "%{$name}%"]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Project::fromArray($row), $rows);
    }

    /**
     * Create a new project.
     *
     * @param Project $project Project entity to create
     * @return Project Created project with ID set
     */
    public function create(Project $project): Project
    {
        $sql = "INSERT INTO projects (name, description, status, created_at, updated_at)
                VALUES (:name, :description, :status, NOW(), NOW())";

        $this->executeQuery($sql, [
            'name' => $project->getName(),
            'description' => $project->getDescription(),
            'status' => $project->getStatus(),
        ]);

        $project->setId($this->lastInsertId());

        return $this->findById($project->getId(), true);
    }

    /**
     * Update an existing project.
     *
     * @param Project $project Project entity to update
     * @return bool True if update was successful
     */
    public function update(Project $project): bool
    {
        if ($project->getId() === null) {
            return false;
        }

        $sql = "UPDATE projects
                SET name = :name,
                    description = :description,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id";

        $stmt = $this->executeQuery($sql, [
            'id' => $project->getId(),
            'name' => $project->getName(),
            'description' => $project->getDescription(),
            'status' => $project->getStatus(),
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Archive a project (set status to archived).
     *
     * @param int $id Project ID
     * @return bool True if successful
     */
    public function archive(int $id): bool
    {
        $sql = "UPDATE projects SET status = :status, updated_at = NOW() WHERE id = :id";

        $stmt = $this->executeQuery($sql, [
            'id' => $id,
            'status' => Project::STATUS_ARCHIVED,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Mark a project as completed.
     *
     * @param int $id Project ID
     * @return bool True if successful
     */
    public function complete(int $id): bool
    {
        $sql = "UPDATE projects SET status = :status, updated_at = NOW() WHERE id = :id";

        $stmt = $this->executeQuery($sql, [
            'id' => $id,
            'status' => Project::STATUS_COMPLETED,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Reactivate a project (set status to active).
     *
     * @param int $id Project ID
     * @return bool True if successful
     */
    public function activate(int $id): bool
    {
        $sql = "UPDATE projects SET status = :status, updated_at = NOW() WHERE id = :id";

        $stmt = $this->executeQuery($sql, [
            'id' => $id,
            'status' => Project::STATUS_ACTIVE,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Count projects by status.
     *
     * @param bool $includeDeleted Whether to include soft-deleted projects
     * @return array<string, int> Count by status
     */
    public function countByStatus(bool $includeDeleted = false): array
    {
        $softDeleteCond = $this->softDeleteCondition($includeDeleted);
        $where = $softDeleteCond !== '' ? "WHERE {$softDeleteCond}" : '';

        $sql = "SELECT status, COUNT(*) as count FROM projects {$where} GROUP BY status";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int)$row['count'];
        }

        return $counts;
    }
}
