<?php

declare(strict_types=1);

namespace Snaply\Services;

use Snaply\Api\ApiException;
use Snaply\Models\Comment;
use Snaply\Repositories\CommentRepository;
use Snaply\Repositories\SnapshotRepository;
use Snaply\Repositories\PageRepository;
use Snaply\Repositories\ProjectRepository;
use InvalidArgumentException;

/**
 * Service layer for comment operations.
 *
 * Handles comment creation with normalised coordinates provided by the front-end.
 * Coordinates are expected to be normalised between 0.0 and 1.0.
 */
class CommentService
{
    private CommentRepository $commentRepository;
    private SnapshotRepository $snapshotRepository;
    private PageRepository $pageRepository;
    private ProjectRepository $projectRepository;

    public function __construct(
        ?CommentRepository $commentRepository = null,
        ?SnapshotRepository $snapshotRepository = null,
        ?PageRepository $pageRepository = null,
        ?ProjectRepository $projectRepository = null
    ) {
        $this->commentRepository = $commentRepository ?? new CommentRepository();
        $this->snapshotRepository = $snapshotRepository ?? new SnapshotRepository();
        $this->pageRepository = $pageRepository ?? new PageRepository();
        $this->projectRepository = $projectRepository ?? new ProjectRepository();
    }

    /**
     * Create a new comment on a snapshot.
     *
     * Accepts normalised coordinates (0.0-1.0) from the front-end.
     *
     * @param int $snapshotId Parent snapshot ID
     * @param array $data Comment data:
     *   - author_name (required): Comment author's name
     *   - content (required): Comment text
     *   - author_email (optional): Author's email
     *   - x_norm (optional): Normalised X coordinate (0.0-1.0)
     *   - y_norm (optional): Normalised Y coordinate (0.0-1.0)
     * @return Comment Created comment
     * @throws ApiException If validation fails or snapshot not accessible
     */
    public function create(int $snapshotId, array $data): Comment
    {
        $this->validateSnapshotAccessible($snapshotId);
        $this->validateCommentData($data, true);

        $comment = new Comment(
            $snapshotId,
            $data['author_name'],
            $data['content']
        );

        if (isset($data['author_email'])) {
            $comment->setAuthorEmail($data['author_email']);
        }

        // Set normalised coordinates if provided
        if (isset($data['x_norm'])) {
            try {
                $comment->setXNorm((float) $data['x_norm']);
            } catch (InvalidArgumentException $e) {
                throw ApiException::validationError('Validation failed', [
                    'x_norm' => $e->getMessage(),
                ]);
            }
        }

        if (isset($data['y_norm'])) {
            try {
                $comment->setYNorm((float) $data['y_norm']);
            } catch (InvalidArgumentException $e) {
                throw ApiException::validationError('Validation failed', [
                    'y_norm' => $e->getMessage(),
                ]);
            }
        }

        return $this->commentRepository->create($comment);
    }

    /**
     * Get a comment by ID.
     *
     * @param int $id Comment ID
     * @param bool $checkParentDeleted Check if parent snapshot's page/project is deleted
     * @return Comment
     * @throws ApiException If comment not found or parent is deleted
     */
    public function get(int $id, bool $checkParentDeleted = true): Comment
    {
        $comment = $this->commentRepository->findById($id);

        if ($comment === null) {
            throw ApiException::notFound('Comment', $id);
        }

        if ($checkParentDeleted) {
            $this->validateSnapshotAccessible($comment->getSnapshotId());
        }

        return $comment;
    }

    /**
     * List comments for a snapshot.
     *
     * @param int $snapshotId Snapshot ID
     * @param bool $checkParentDeleted Check if parent page/project is deleted
     * @return Comment[]
     * @throws ApiException If snapshot not accessible
     */
    public function listBySnapshot(int $snapshotId, bool $checkParentDeleted = true): array
    {
        if ($checkParentDeleted) {
            $this->validateSnapshotAccessible($snapshotId);
        } else {
            // At least verify snapshot exists
            $snapshot = $this->snapshotRepository->findById($snapshotId);
            if ($snapshot === null) {
                throw ApiException::notFound('Snapshot', $snapshotId);
            }
        }

        return $this->commentRepository->findBySnapshotId($snapshotId);
    }

    /**
     * Update a comment.
     *
     * @param int $id Comment ID
     * @param array $data Update data (content, author_name, author_email, x_norm, y_norm)
     * @return Comment Updated comment
     * @throws ApiException If comment not found or validation fails
     */
    public function update(int $id, array $data): Comment
    {
        $comment = $this->get($id);
        $this->validateCommentData($data, false);

        if (isset($data['author_name'])) {
            $comment->setAuthorName($data['author_name']);
        }

        if (isset($data['content'])) {
            $comment->setContent($data['content']);
        }

        if (array_key_exists('author_email', $data)) {
            $comment->setAuthorEmail($data['author_email']);
        }

        if (array_key_exists('x_norm', $data)) {
            try {
                $comment->setXNorm($data['x_norm'] !== null ? (float) $data['x_norm'] : null);
            } catch (InvalidArgumentException $e) {
                throw ApiException::validationError('Validation failed', [
                    'x_norm' => $e->getMessage(),
                ]);
            }
        }

        if (array_key_exists('y_norm', $data)) {
            try {
                $comment->setYNorm($data['y_norm'] !== null ? (float) $data['y_norm'] : null);
            } catch (InvalidArgumentException $e) {
                throw ApiException::validationError('Validation failed', [
                    'y_norm' => $e->getMessage(),
                ]);
            }
        }

        $this->commentRepository->update($comment);

        return $this->commentRepository->findById($id);
    }

    /**
     * Delete a comment.
     *
     * @param int $id Comment ID
     * @return bool Success
     * @throws ApiException If comment not found
     */
    public function delete(int $id): bool
    {
        $comment = $this->commentRepository->findById($id);

        if ($comment === null) {
            throw ApiException::notFound('Comment', $id);
        }

        return $this->commentRepository->delete($id);
    }

    /**
     * Validate that a snapshot is accessible.
     *
     * Checks that the snapshot exists and its parent page and project are not soft-deleted.
     *
     * @throws ApiException If snapshot not accessible
     */
    private function validateSnapshotAccessible(int $snapshotId): void
    {
        $snapshot = $this->snapshotRepository->findById($snapshotId);
        if ($snapshot === null) {
            throw ApiException::notFound('Snapshot', $snapshotId);
        }

        $page = $this->pageRepository->findById($snapshot->getPageId());
        if ($page === null) {
            throw ApiException::badRequest('Cannot access comment: parent page is deleted');
        }

        $project = $this->projectRepository->findById($page->getProjectId());
        if ($project === null) {
            throw ApiException::badRequest('Cannot access comment: parent project is deleted');
        }
    }

    /**
     * Validate comment data.
     *
     * @param array $data Data to validate
     * @param bool $isCreate Whether this is for creation
     * @throws ApiException If validation fails
     */
    private function validateCommentData(array $data, bool $isCreate): void
    {
        $errors = [];

        // Required fields for creation
        if ($isCreate) {
            if (!isset($data['author_name'])) {
                $errors['author_name'] = 'Author name is required';
            }
            if (!isset($data['content'])) {
                $errors['content'] = 'Content is required';
            }
        }

        // Validate author_name if provided
        if (isset($data['author_name'])) {
            $authorName = trim($data['author_name']);
            if ($authorName === '') {
                $errors['author_name'] = 'Author name cannot be empty';
            } elseif (strlen($authorName) > 255) {
                $errors['author_name'] = 'Author name cannot exceed 255 characters';
            }
        }

        // Validate content if provided
        if (isset($data['content'])) {
            $content = trim($data['content']);
            if ($content === '') {
                $errors['content'] = 'Content cannot be empty';
            } elseif (strlen($content) > 65535) {
                $errors['content'] = 'Content is too long';
            }
        }

        // Validate email if provided
        if (isset($data['author_email']) && $data['author_email'] !== null) {
            if (!filter_var($data['author_email'], FILTER_VALIDATE_EMAIL)) {
                $errors['author_email'] = 'Invalid email address';
            } elseif (strlen($data['author_email']) > 255) {
                $errors['author_email'] = 'Email cannot exceed 255 characters';
            }
        }

        // Validate coordinates if provided (additional validation beyond model)
        if (isset($data['x_norm']) && $data['x_norm'] !== null) {
            if (!is_numeric($data['x_norm'])) {
                $errors['x_norm'] = 'X coordinate must be a number';
            }
        }

        if (isset($data['y_norm']) && $data['y_norm'] !== null) {
            if (!is_numeric($data['y_norm'])) {
                $errors['y_norm'] = 'Y coordinate must be a number';
            }
        }

        if (!empty($errors)) {
            throw ApiException::validationError('Validation failed', $errors);
        }
    }
}
