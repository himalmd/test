<?php

declare(strict_types=1);

namespace Snaply\Repositories;

use Snaply\Models\Snapshot;

/**
 * Repository for Snapshot entity CRUD operations.
 */
class SnapshotRepository extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'snapshots';
    }

    /**
     * Find all snapshots.
     *
     * @param bool $includeDeleted Whether to include soft-deleted snapshots
     * @return Snapshot[]
     */
    public function findAll(bool $includeDeleted = false): array
    {
        $where = $this->buildWhereClause([], $includeDeleted);
        $sql = "SELECT * FROM snapshots {$where} ORDER BY created_at DESC";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Snapshot::fromArray($row), $rows);
    }

    /**
     * Find a snapshot by ID.
     *
     * @param int  $id             Snapshot ID
     * @param bool $includeDeleted Whether to include soft-deleted snapshots
     * @return Snapshot|null
     */
    public function findById(int $id, bool $includeDeleted = false): ?Snapshot
    {
        $where = $this->buildWhereClause(['id' => $id], $includeDeleted);
        $sql = "SELECT * FROM snapshots {$where}";

        $stmt = $this->executeQuery($sql, ['id' => $id]);
        $row = $stmt->fetch();

        return $row ? Snapshot::fromArray($row) : null;
    }

    /**
     * Find snapshots by page ID.
     *
     * @param int  $pageId         Page ID
     * @param bool $includeDeleted Whether to include soft-deleted snapshots
     * @return Snapshot[]
     */
    public function findByPageId(int $pageId, bool $includeDeleted = false): array
    {
        $where = $this->buildWhereClause(['page_id' => $pageId], $includeDeleted);
        $sql = "SELECT * FROM snapshots {$where} ORDER BY version DESC, created_at DESC";

        $stmt = $this->executeQuery($sql, ['page_id' => $pageId]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Snapshot::fromArray($row), $rows);
    }

    /**
     * Find snapshots by page ID, ensuring the page and project are also active.
     *
     * @param int  $pageId         Page ID
     * @param bool $includeDeleted Whether to include soft-deleted records
     * @return Snapshot[]
     */
    public function findByActivePage(int $pageId, bool $includeDeleted = false): array
    {
        $conditions = ['s.page_id = :page_id'];

        if (!$includeDeleted) {
            $conditions[] = 's.deleted_at IS NULL';
            $conditions[] = 'p.deleted_at IS NULL';
            $conditions[] = 'pr.deleted_at IS NULL';
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $sql = "SELECT s.* FROM snapshots s
                INNER JOIN pages p ON s.page_id = p.id
                INNER JOIN projects pr ON p.project_id = pr.id
                {$where}
                ORDER BY s.version DESC, s.created_at DESC";

        $stmt = $this->executeQuery($sql, ['page_id' => $pageId]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Snapshot::fromArray($row), $rows);
    }

    /**
     * Find the latest snapshot for a page.
     *
     * @param int  $pageId         Page ID
     * @param bool $includeDeleted Whether to include soft-deleted snapshots
     * @return Snapshot|null
     */
    public function findLatestByPageId(int $pageId, bool $includeDeleted = false): ?Snapshot
    {
        $where = $this->buildWhereClause(['page_id' => $pageId], $includeDeleted);
        $sql = "SELECT * FROM snapshots {$where} ORDER BY version DESC, created_at DESC LIMIT 1";

        $stmt = $this->executeQuery($sql, ['page_id' => $pageId]);
        $row = $stmt->fetch();

        return $row ? Snapshot::fromArray($row) : null;
    }

    /**
     * Find a snapshot by page ID and version.
     *
     * @param int  $pageId         Page ID
     * @param int  $version        Version number
     * @param bool $includeDeleted Whether to include soft-deleted snapshots
     * @return Snapshot|null
     */
    public function findByVersion(int $pageId, int $version, bool $includeDeleted = false): ?Snapshot
    {
        $conditions = ['page_id' => $pageId, 'version' => $version];
        $where = $this->buildWhereClause($conditions, $includeDeleted);

        $sql = "SELECT * FROM snapshots {$where}";

        $stmt = $this->executeQuery($sql, $conditions);
        $row = $stmt->fetch();

        return $row ? Snapshot::fromArray($row) : null;
    }

    /**
     * Find snapshots by media ID.
     *
     * @param int  $mediaId        Media ID
     * @param bool $includeDeleted Whether to include soft-deleted snapshots
     * @return Snapshot[]
     */
    public function findByMediaId(int $mediaId, bool $includeDeleted = false): array
    {
        $where = $this->buildWhereClause(['media_id' => $mediaId], $includeDeleted);
        $sql = "SELECT * FROM snapshots {$where} ORDER BY created_at DESC";

        $stmt = $this->executeQuery($sql, ['media_id' => $mediaId]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Snapshot::fromArray($row), $rows);
    }

    /**
     * Create a new snapshot.
     *
     * @param Snapshot $snapshot Snapshot entity to create
     * @return Snapshot Created snapshot with ID set
     */
    public function create(Snapshot $snapshot): Snapshot
    {
        $sql = "INSERT INTO snapshots (page_id, media_id, version, width, height, captured_at, created_at, updated_at)
                VALUES (:page_id, :media_id, :version, :width, :height, :captured_at, NOW(), NOW())";

        $this->executeQuery($sql, [
            'page_id' => $snapshot->getPageId(),
            'media_id' => $snapshot->getMediaId(),
            'version' => $snapshot->getVersion(),
            'width' => $snapshot->getWidth(),
            'height' => $snapshot->getHeight(),
            'captured_at' => $snapshot->getCapturedAt()?->format('Y-m-d H:i:s'),
        ]);

        $snapshot->setId($this->lastInsertId());

        return $this->findById($snapshot->getId(), true);
    }

    /**
     * Create a new snapshot with auto-incremented version.
     *
     * @param Snapshot $snapshot Snapshot entity (version will be set automatically)
     * @return Snapshot Created snapshot with ID and version set
     */
    public function createWithAutoVersion(Snapshot $snapshot): Snapshot
    {
        $nextVersion = $this->getNextVersion($snapshot->getPageId());
        $snapshot->setVersion($nextVersion);

        return $this->create($snapshot);
    }

    /**
     * Update an existing snapshot.
     *
     * @param Snapshot $snapshot Snapshot entity to update
     * @return bool True if update was successful
     */
    public function update(Snapshot $snapshot): bool
    {
        if ($snapshot->getId() === null) {
            return false;
        }

        $sql = "UPDATE snapshots
                SET page_id = :page_id,
                    media_id = :media_id,
                    version = :version,
                    width = :width,
                    height = :height,
                    captured_at = :captured_at,
                    updated_at = NOW()
                WHERE id = :id";

        $stmt = $this->executeQuery($sql, [
            'id' => $snapshot->getId(),
            'page_id' => $snapshot->getPageId(),
            'media_id' => $snapshot->getMediaId(),
            'version' => $snapshot->getVersion(),
            'width' => $snapshot->getWidth(),
            'height' => $snapshot->getHeight(),
            'captured_at' => $snapshot->getCapturedAt()?->format('Y-m-d H:i:s'),
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Get the next version number for a page.
     *
     * @param int $pageId Page ID
     * @return int Next version number
     */
    public function getNextVersion(int $pageId): int
    {
        $sql = "SELECT COALESCE(MAX(version), 0) + 1 FROM snapshots WHERE page_id = :page_id";

        $stmt = $this->executeQuery($sql, ['page_id' => $pageId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Count snapshots by page.
     *
     * @param int  $pageId         Page ID
     * @param bool $includeDeleted Whether to include soft-deleted snapshots
     * @return int
     */
    public function countByPage(int $pageId, bool $includeDeleted = false): int
    {
        $where = $this->buildWhereClause(['page_id' => $pageId], $includeDeleted);
        $sql = "SELECT COUNT(*) FROM snapshots {$where}";

        $stmt = $this->executeQuery($sql, ['page_id' => $pageId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get snapshot with related page and project data.
     *
     * @param int  $id             Snapshot ID
     * @param bool $includeDeleted Whether to include soft-deleted records
     * @return array|null Snapshot data with page and project info
     */
    public function findWithRelations(int $id, bool $includeDeleted = false): ?array
    {
        $conditions = ['s.id = :id'];

        if (!$includeDeleted) {
            $conditions[] = 's.deleted_at IS NULL';
            $conditions[] = 'p.deleted_at IS NULL';
            $conditions[] = 'pr.deleted_at IS NULL';
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $sql = "SELECT s.*,
                       p.title as page_title, p.url as page_url, p.slug as page_slug,
                       pr.name as project_name, pr.id as project_id
                FROM snapshots s
                INNER JOIN pages p ON s.page_id = p.id
                INNER JOIN projects pr ON p.project_id = pr.id
                {$where}";

        $stmt = $this->executeQuery($sql, ['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
