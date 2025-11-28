<?php

declare(strict_types=1);

namespace Snaply\Repositories;

use Snaply\Models\Page;

/**
 * Repository for Page entity CRUD operations.
 */
class PageRepository extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'pages';
    }

    /**
     * Find all pages.
     *
     * @param bool $includeDeleted Whether to include soft-deleted pages
     * @return Page[]
     */
    public function findAll(bool $includeDeleted = false): array
    {
        $where = $this->buildWhereClause([], $includeDeleted);
        $sql = "SELECT * FROM pages {$where} ORDER BY created_at DESC";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Page::fromArray($row), $rows);
    }

    /**
     * Find a page by ID.
     *
     * @param int  $id             Page ID
     * @param bool $includeDeleted Whether to include soft-deleted pages
     * @return Page|null
     */
    public function findById(int $id, bool $includeDeleted = false): ?Page
    {
        $where = $this->buildWhereClause(['id' => $id], $includeDeleted);
        $sql = "SELECT * FROM pages {$where}";

        $stmt = $this->executeQuery($sql, ['id' => $id]);
        $row = $stmt->fetch();

        return $row ? Page::fromArray($row) : null;
    }

    /**
     * Find pages by project ID.
     *
     * @param int  $projectId      Project ID
     * @param bool $includeDeleted Whether to include soft-deleted pages
     * @return Page[]
     */
    public function findByProjectId(int $projectId, bool $includeDeleted = false): array
    {
        $where = $this->buildWhereClause(['project_id' => $projectId], $includeDeleted);
        $sql = "SELECT * FROM pages {$where} ORDER BY created_at DESC";

        $stmt = $this->executeQuery($sql, ['project_id' => $projectId]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Page::fromArray($row), $rows);
    }

    /**
     * Find pages by project ID, ensuring the project is also active.
     *
     * @param int  $projectId      Project ID
     * @param bool $includeDeleted Whether to include soft-deleted pages/projects
     * @return Page[]
     */
    public function findByActiveProject(int $projectId, bool $includeDeleted = false): array
    {
        $conditions = ['p.project_id = :project_id'];

        if (!$includeDeleted) {
            $conditions[] = 'p.deleted_at IS NULL';
            $conditions[] = 'pr.deleted_at IS NULL';
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $sql = "SELECT p.* FROM pages p
                INNER JOIN projects pr ON p.project_id = pr.id
                {$where}
                ORDER BY p.created_at DESC";

        $stmt = $this->executeQuery($sql, ['project_id' => $projectId]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Page::fromArray($row), $rows);
    }

    /**
     * Find a page by slug within a project.
     *
     * @param int    $projectId      Project ID
     * @param string $slug           Page slug
     * @param bool   $includeDeleted Whether to include soft-deleted pages
     * @return Page|null
     */
    public function findBySlug(int $projectId, string $slug, bool $includeDeleted = false): ?Page
    {
        $conditions = ['project_id' => $projectId, 'slug' => $slug];
        $where = $this->buildWhereClause($conditions, $includeDeleted);

        $sql = "SELECT * FROM pages {$where}";

        $stmt = $this->executeQuery($sql, $conditions);
        $row = $stmt->fetch();

        return $row ? Page::fromArray($row) : null;
    }

    /**
     * Find a page by URL within a project.
     *
     * @param int    $projectId      Project ID
     * @param string $url            Page URL
     * @param bool   $includeDeleted Whether to include soft-deleted pages
     * @return Page|null
     */
    public function findByUrl(int $projectId, string $url, bool $includeDeleted = false): ?Page
    {
        $conditions = ['project_id' => $projectId, 'url' => $url];
        $where = $this->buildWhereClause($conditions, $includeDeleted);

        $sql = "SELECT * FROM pages {$where}";

        $stmt = $this->executeQuery($sql, $conditions);
        $row = $stmt->fetch();

        return $row ? Page::fromArray($row) : null;
    }

    /**
     * Create a new page.
     *
     * @param Page $page Page entity to create
     * @return Page Created page with ID set
     */
    public function create(Page $page): Page
    {
        $sql = "INSERT INTO pages (project_id, url, slug, title, description, created_at, updated_at)
                VALUES (:project_id, :url, :slug, :title, :description, NOW(), NOW())";

        $this->executeQuery($sql, [
            'project_id' => $page->getProjectId(),
            'url' => $page->getUrl(),
            'slug' => $page->getSlug(),
            'title' => $page->getTitle(),
            'description' => $page->getDescription(),
        ]);

        $page->setId($this->lastInsertId());

        return $this->findById($page->getId(), true);
    }

    /**
     * Update an existing page.
     *
     * @param Page $page Page entity to update
     * @return bool True if update was successful
     */
    public function update(Page $page): bool
    {
        if ($page->getId() === null) {
            return false;
        }

        $sql = "UPDATE pages
                SET project_id = :project_id,
                    url = :url,
                    slug = :slug,
                    title = :title,
                    description = :description,
                    updated_at = NOW()
                WHERE id = :id";

        $stmt = $this->executeQuery($sql, [
            'id' => $page->getId(),
            'project_id' => $page->getProjectId(),
            'url' => $page->getUrl(),
            'slug' => $page->getSlug(),
            'title' => $page->getTitle(),
            'description' => $page->getDescription(),
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Check if a slug is available within a project.
     *
     * @param int         $projectId Project ID
     * @param string      $slug      Slug to check
     * @param int|null    $excludeId Page ID to exclude (for updates)
     * @return bool True if slug is available
     */
    public function isSlugAvailable(int $projectId, string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM pages WHERE project_id = :project_id AND slug = :slug";
        $params = ['project_id' => $projectId, 'slug' => $slug];

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetchColumn() === false;
    }

    /**
     * Generate a unique slug for a page within a project.
     *
     * @param int    $projectId Project ID
     * @param string $baseSlug  Base slug to use
     * @param int|null $excludeId Page ID to exclude (for updates)
     * @return string Unique slug
     */
    public function generateUniqueSlug(int $projectId, string $baseSlug, ?int $excludeId = null): string
    {
        $slug = $baseSlug;
        $counter = 1;

        while (!$this->isSlugAvailable($projectId, $slug, $excludeId)) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Count pages by project.
     *
     * @param int  $projectId      Project ID
     * @param bool $includeDeleted Whether to include soft-deleted pages
     * @return int
     */
    public function countByProject(int $projectId, bool $includeDeleted = false): int
    {
        $where = $this->buildWhereClause(['project_id' => $projectId], $includeDeleted);
        $sql = "SELECT COUNT(*) FROM pages {$where}";

        $stmt = $this->executeQuery($sql, ['project_id' => $projectId]);
        return (int)$stmt->fetchColumn();
    }
}
