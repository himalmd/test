<?php

declare(strict_types=1);

namespace Snaply\Repositories;

use Snaply\Models\Comment;

/**
 * Repository for Comment entity CRUD operations.
 *
 * Note: Comments do not support soft delete - they are preserved even when
 * parent entities (snapshots, pages, projects) are soft-deleted. This ensures
 * discussion history is never lost.
 */
class CommentRepository extends BaseRepository
{
    protected function getTableName(): string
    {
        return 'comments';
    }

    /**
     * Comments do not support soft delete.
     */
    protected function supportsSoftDelete(): bool
    {
        return false;
    }

    /**
     * Find all comments.
     *
     * @return Comment[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM comments ORDER BY created_at ASC";

        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Comment::fromArray($row), $rows);
    }

    /**
     * Find a comment by ID.
     *
     * @param int $id Comment ID
     * @return Comment|null
     */
    public function findById(int $id): ?Comment
    {
        $sql = "SELECT * FROM comments WHERE id = :id";

        $stmt = $this->executeQuery($sql, ['id' => $id]);
        $row = $stmt->fetch();

        return $row ? Comment::fromArray($row) : null;
    }

    /**
     * Find all comments for a snapshot.
     *
     * @param int $snapshotId Snapshot ID
     * @return Comment[]
     */
    public function findBySnapshotId(int $snapshotId): array
    {
        $sql = "SELECT * FROM comments WHERE snapshot_id = :snapshot_id ORDER BY created_at ASC";

        $stmt = $this->executeQuery($sql, ['snapshot_id' => $snapshotId]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Comment::fromArray($row), $rows);
    }

    /**
     * Find comments for a snapshot on an active page/project.
     *
     * This is the standard UI query - excludes comments on soft-deleted parents.
     *
     * @param int  $snapshotId     Snapshot ID
     * @param bool $includeDeleted Include comments on soft-deleted parents
     * @return Comment[]
     */
    public function findByActiveSnapshot(int $snapshotId, bool $includeDeleted = false): array
    {
        if ($includeDeleted) {
            return $this->findBySnapshotId($snapshotId);
        }

        $sql = "SELECT c.* FROM comments c
                INNER JOIN snapshots s ON c.snapshot_id = s.id
                INNER JOIN pages p ON s.page_id = p.id
                INNER JOIN projects pr ON p.project_id = pr.id
                WHERE c.snapshot_id = :snapshot_id
                  AND s.deleted_at IS NULL
                  AND p.deleted_at IS NULL
                  AND pr.deleted_at IS NULL
                ORDER BY c.created_at ASC";

        $stmt = $this->executeQuery($sql, ['snapshot_id' => $snapshotId]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Comment::fromArray($row), $rows);
    }

    /**
     * Find top-level comments (not replies) for a snapshot.
     *
     * @param int $snapshotId Snapshot ID
     * @return Comment[]
     */
    public function findTopLevelBySnapshotId(int $snapshotId): array
    {
        $sql = "SELECT * FROM comments
                WHERE snapshot_id = :snapshot_id AND parent_id IS NULL
                ORDER BY created_at ASC";

        $stmt = $this->executeQuery($sql, ['snapshot_id' => $snapshotId]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Comment::fromArray($row), $rows);
    }

    /**
     * Find replies to a comment.
     *
     * @param int $parentId Parent comment ID
     * @return Comment[]
     */
    public function findReplies(int $parentId): array
    {
        $sql = "SELECT * FROM comments WHERE parent_id = :parent_id ORDER BY created_at ASC";

        $stmt = $this->executeQuery($sql, ['parent_id' => $parentId]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Comment::fromArray($row), $rows);
    }

    /**
     * Find comments by resolution status.
     *
     * @param int  $snapshotId Snapshot ID
     * @param bool $isResolved Resolution status
     * @return Comment[]
     */
    public function findByResolutionStatus(int $snapshotId, bool $isResolved): array
    {
        $sql = "SELECT * FROM comments
                WHERE snapshot_id = :snapshot_id AND is_resolved = :is_resolved
                ORDER BY created_at ASC";

        $stmt = $this->executeQuery($sql, [
            'snapshot_id' => $snapshotId,
            'is_resolved' => $isResolved ? 1 : 0,
        ]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Comment::fromArray($row), $rows);
    }

    /**
     * Find unresolved comments for a snapshot.
     *
     * @param int $snapshotId Snapshot ID
     * @return Comment[]
     */
    public function findUnresolved(int $snapshotId): array
    {
        return $this->findByResolutionStatus($snapshotId, false);
    }

    /**
     * Find resolved comments for a snapshot.
     *
     * @param int $snapshotId Snapshot ID
     * @return Comment[]
     */
    public function findResolved(int $snapshotId): array
    {
        return $this->findByResolutionStatus($snapshotId, true);
    }

    /**
     * Find comments with coordinates (positioned comments).
     *
     * @param int $snapshotId Snapshot ID
     * @return Comment[]
     */
    public function findWithCoordinates(int $snapshotId): array
    {
        $sql = "SELECT * FROM comments
                WHERE snapshot_id = :snapshot_id
                  AND x_norm IS NOT NULL
                  AND y_norm IS NOT NULL
                ORDER BY created_at ASC";

        $stmt = $this->executeQuery($sql, ['snapshot_id' => $snapshotId]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Comment::fromArray($row), $rows);
    }

    /**
     * Find comments by author email.
     *
     * @param string $authorEmail Author email
     * @return Comment[]
     */
    public function findByAuthorEmail(string $authorEmail): array
    {
        $sql = "SELECT * FROM comments WHERE author_email = :author_email ORDER BY created_at DESC";

        $stmt = $this->executeQuery($sql, ['author_email' => $authorEmail]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => Comment::fromArray($row), $rows);
    }

    /**
     * Create a new comment.
     *
     * @param Comment $comment Comment entity to create
     * @return Comment Created comment with ID set
     */
    public function create(Comment $comment): Comment
    {
        $sql = "INSERT INTO comments
                (snapshot_id, parent_id, author_name, author_email, content, x_norm, y_norm, is_resolved, created_at, updated_at)
                VALUES
                (:snapshot_id, :parent_id, :author_name, :author_email, :content, :x_norm, :y_norm, :is_resolved, NOW(), NOW())";

        $this->executeQuery($sql, [
            'snapshot_id' => $comment->getSnapshotId(),
            'parent_id' => $comment->getParentId(),
            'author_name' => $comment->getAuthorName(),
            'author_email' => $comment->getAuthorEmail(),
            'content' => $comment->getContent(),
            'x_norm' => $comment->getXNorm(),
            'y_norm' => $comment->getYNorm(),
            'is_resolved' => $comment->isResolved() ? 1 : 0,
        ]);

        $comment->setId($this->lastInsertId());

        return $this->findById($comment->getId());
    }

    /**
     * Update an existing comment.
     *
     * @param Comment $comment Comment entity to update
     * @return bool True if update was successful
     */
    public function update(Comment $comment): bool
    {
        if ($comment->getId() === null) {
            return false;
        }

        $sql = "UPDATE comments
                SET content = :content,
                    x_norm = :x_norm,
                    y_norm = :y_norm,
                    is_resolved = :is_resolved,
                    updated_at = NOW()
                WHERE id = :id";

        $stmt = $this->executeQuery($sql, [
            'id' => $comment->getId(),
            'content' => $comment->getContent(),
            'x_norm' => $comment->getXNorm(),
            'y_norm' => $comment->getYNorm(),
            'is_resolved' => $comment->isResolved() ? 1 : 0,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a comment (permanent deletion).
     *
     * @param int $id Comment ID
     * @return bool True if deletion was successful
     */
    public function delete(int $id): bool
    {
        return $this->hardDelete($id);
    }

    /**
     * Resolve a comment thread.
     *
     * @param int $id Comment ID
     * @return bool True if successful
     */
    public function resolve(int $id): bool
    {
        $sql = "UPDATE comments SET is_resolved = 1, updated_at = NOW() WHERE id = :id";

        $stmt = $this->executeQuery($sql, ['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Unresolve a comment thread.
     *
     * @param int $id Comment ID
     * @return bool True if successful
     */
    public function unresolve(int $id): bool
    {
        $sql = "UPDATE comments SET is_resolved = 0, updated_at = NOW() WHERE id = :id";

        $stmt = $this->executeQuery($sql, ['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Update comment coordinates.
     *
     * @param int        $id    Comment ID
     * @param float|null $xNorm Normalised X coordinate (0.0 to 1.0)
     * @param float|null $yNorm Normalised Y coordinate (0.0 to 1.0)
     * @return bool True if successful
     */
    public function updateCoordinates(int $id, ?float $xNorm, ?float $yNorm): bool
    {
        // Validate coordinates
        if ($xNorm !== null && ($xNorm < 0.0 || $xNorm > 1.0)) {
            throw new \InvalidArgumentException('X coordinate must be between 0.0 and 1.0');
        }
        if ($yNorm !== null && ($yNorm < 0.0 || $yNorm > 1.0)) {
            throw new \InvalidArgumentException('Y coordinate must be between 0.0 and 1.0');
        }

        $sql = "UPDATE comments SET x_norm = :x_norm, y_norm = :y_norm, updated_at = NOW() WHERE id = :id";

        $stmt = $this->executeQuery($sql, [
            'id' => $id,
            'x_norm' => $xNorm,
            'y_norm' => $yNorm,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Count comments by snapshot.
     *
     * @param int $snapshotId Snapshot ID
     * @return int
     */
    public function countBySnapshot(int $snapshotId): int
    {
        $sql = "SELECT COUNT(*) FROM comments WHERE snapshot_id = :snapshot_id";

        $stmt = $this->executeQuery($sql, ['snapshot_id' => $snapshotId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Count unresolved comments by snapshot.
     *
     * @param int $snapshotId Snapshot ID
     * @return int
     */
    public function countUnresolved(int $snapshotId): int
    {
        $sql = "SELECT COUNT(*) FROM comments WHERE snapshot_id = :snapshot_id AND is_resolved = 0";

        $stmt = $this->executeQuery($sql, ['snapshot_id' => $snapshotId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get comment thread (comment with all its replies).
     *
     * @param int $commentId Comment ID
     * @return Comment[] Comment followed by its replies
     */
    public function getThread(int $commentId): array
    {
        $comment = $this->findById($commentId);
        if ($comment === null) {
            return [];
        }

        // If this is a reply, get the parent thread instead
        if ($comment->getParentId() !== null) {
            return $this->getThread($comment->getParentId());
        }

        $replies = $this->findReplies($commentId);
        return array_merge([$comment], $replies);
    }

    /**
     * Get all comments for a snapshot organized as threads.
     *
     * @param int $snapshotId Snapshot ID
     * @return array<int, array{comment: Comment, replies: Comment[]}>
     */
    public function getThreadsBySnapshot(int $snapshotId): array
    {
        $topLevel = $this->findTopLevelBySnapshotId($snapshotId);
        $threads = [];

        foreach ($topLevel as $comment) {
            $threads[$comment->getId()] = [
                'comment' => $comment,
                'replies' => $this->findReplies($comment->getId()),
            ];
        }

        return $threads;
    }
}
