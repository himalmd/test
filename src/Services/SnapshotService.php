<?php

declare(strict_types=1);

namespace Snaply\Services;

use DateTimeImmutable;
use Snaply\Api\ApiException;
use Snaply\Config\Database;
use Snaply\Models\Snapshot;
use Snaply\Repositories\SnapshotRepository;
use Snaply\Repositories\PageRepository;
use Snaply\Repositories\ProjectRepository;
use Snaply\Repositories\CommentRepository;
use Snaply\Repositories\MediaRepository;
use Snaply\Services\Media\MediaStorageService;
use Snaply\Services\Media\UploadedFile;
use PDO;
use Throwable;

/**
 * Service layer for snapshot operations.
 *
 * Orchestrates snapshot creation with media storage, ensuring width, height,
 * and media references are properly saved.
 */
class SnapshotService
{
    private SnapshotRepository $snapshotRepository;
    private PageRepository $pageRepository;
    private ProjectRepository $projectRepository;
    private CommentRepository $commentRepository;
    private MediaRepository $mediaRepository;
    private MediaStorageService $mediaStorage;

    public function __construct(
        ?SnapshotRepository $snapshotRepository = null,
        ?PageRepository $pageRepository = null,
        ?ProjectRepository $projectRepository = null,
        ?CommentRepository $commentRepository = null,
        ?MediaRepository $mediaRepository = null,
        ?MediaStorageService $mediaStorage = null
    ) {
        $this->snapshotRepository = $snapshotRepository ?? new SnapshotRepository();
        $this->pageRepository = $pageRepository ?? new PageRepository();
        $this->projectRepository = $projectRepository ?? new ProjectRepository();
        $this->commentRepository = $commentRepository ?? new CommentRepository();
        $this->mediaRepository = $mediaRepository ?? new MediaRepository();
        $this->mediaStorage = $mediaStorage ?? $this->createDefaultMediaStorage();
    }

    /**
     * Create a snapshot with an uploaded image file.
     *
     * @param int $pageId Parent page ID
     * @param UploadedFile $image Uploaded image file
     * @param array $data Snapshot data with 'width', 'height' (required), 'captured_at' (optional)
     * @return array Snapshot data with media URL
     * @throws ApiException If validation fails or page not found
     */
    public function create(int $pageId, UploadedFile $image, array $data): array
    {
        $this->validatePageAccessible($pageId);
        $this->validateSnapshotData($data);

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            // Store the image and get the media record
            $media = $this->mediaStorage->store($image, [
                'purpose' => 'snapshot',
            ]);

            // Create the snapshot
            $snapshot = new Snapshot();
            $snapshot->setPageId($pageId);
            $snapshot->setMediaId($media->getId());
            $snapshot->setWidth((int) $data['width']);
            $snapshot->setHeight((int) $data['height']);

            if (isset($data['captured_at'])) {
                $snapshot->setCapturedAt(new DateTimeImmutable($data['captured_at']));
            } else {
                $snapshot->setCapturedAt(new DateTimeImmutable());
            }

            $snapshot = $this->snapshotRepository->save($snapshot);

            $pdo->commit();

            return $this->formatSnapshotResponse($snapshot);

        } catch (Throwable $e) {
            $pdo->rollBack();

            // Clean up media if it was created
            if (isset($media)) {
                try {
                    $this->mediaStorage->delete($media->getId());
                } catch (Throwable) {
                    // Ignore cleanup errors
                }
            }

            if ($e instanceof ApiException) {
                throw $e;
            }

            throw ApiException::internalError('Failed to create snapshot: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Create a snapshot from raw image content (e.g., base64 decoded).
     *
     * @param int $pageId Parent page ID
     * @param string $imageContent Raw image binary content
     * @param string $filename Original filename
     * @param string $mimeType Image MIME type
     * @param array $data Snapshot data with 'width', 'height' (required)
     * @return array Snapshot data with media URL
     * @throws ApiException If validation fails
     */
    public function createFromImageContent(
        int $pageId,
        string $imageContent,
        string $filename,
        string $mimeType,
        array $data
    ): array {
        $this->validatePageAccessible($pageId);
        $this->validateSnapshotData($data);

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            // Store the image content
            $media = $this->mediaStorage->storeFromContent(
                $imageContent,
                $filename,
                $mimeType
            );

            // Create the snapshot
            $snapshot = new Snapshot();
            $snapshot->setPageId($pageId);
            $snapshot->setMediaId($media->getId());
            $snapshot->setWidth((int) $data['width']);
            $snapshot->setHeight((int) $data['height']);

            if (isset($data['captured_at'])) {
                $snapshot->setCapturedAt(new DateTimeImmutable($data['captured_at']));
            } else {
                $snapshot->setCapturedAt(new DateTimeImmutable());
            }

            $snapshot = $this->snapshotRepository->save($snapshot);

            $pdo->commit();

            return $this->formatSnapshotResponse($snapshot);

        } catch (Throwable $e) {
            $pdo->rollBack();

            if (isset($media)) {
                try {
                    $this->mediaStorage->delete($media->getId());
                } catch (Throwable) {
                    // Ignore cleanup errors
                }
            }

            if ($e instanceof ApiException) {
                throw $e;
            }

            throw ApiException::internalError('Failed to create snapshot: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Get a snapshot by ID.
     *
     * @param int $id Snapshot ID
     * @param bool $checkParentDeleted Check if parent page/project is deleted
     * @return array Snapshot data with media URL
     * @throws ApiException If snapshot not found
     */
    public function get(int $id, bool $checkParentDeleted = true): array
    {
        $snapshot = $this->snapshotRepository->findById($id);

        if ($snapshot === null) {
            throw ApiException::notFound('Snapshot', $id);
        }

        // Check parent hierarchy if requested
        if ($checkParentDeleted) {
            $page = $this->pageRepository->findById($snapshot->getPageId());
            if ($page === null) {
                throw ApiException::notFound('Snapshot', $id);
            }

            $project = $this->projectRepository->findById($page->getProjectId());
            if ($project === null) {
                throw ApiException::notFound('Snapshot', $id);
            }
        }

        return $this->formatSnapshotResponse($snapshot);
    }

    /**
     * List snapshots for a page.
     *
     * @param int $pageId Page ID
     * @param bool $includeDeletedParent Include if parent page is soft-deleted
     * @return array[] Array of snapshot data with media URLs
     * @throws ApiException If page not found
     */
    public function listByPage(int $pageId, bool $includeDeletedParent = false): array
    {
        // Verify page exists and is accessible
        $page = $this->pageRepository->findById($pageId, $includeDeletedParent);
        if ($page === null) {
            throw ApiException::notFound('Page', $pageId);
        }

        // Check project is not deleted (unless including deleted)
        if (!$includeDeletedParent) {
            $project = $this->projectRepository->findById($page->getProjectId());
            if ($project === null) {
                throw ApiException::notFound('Page', $pageId);
            }
        }

        $snapshots = $this->snapshotRepository->findByPageId($pageId);

        return array_map(
            fn(Snapshot $snapshot) => $this->formatSnapshotResponse($snapshot),
            $snapshots
        );
    }

    /**
     * Delete a snapshot and its associated media.
     *
     * @param int $id Snapshot ID
     * @return bool Success
     * @throws ApiException If snapshot not found
     */
    public function delete(int $id): bool
    {
        $snapshot = $this->snapshotRepository->findById($id);

        if ($snapshot === null) {
            throw ApiException::notFound('Snapshot', $id);
        }

        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            // Delete snapshot first (due to FK constraint)
            $result = $this->snapshotRepository->delete($id);

            // Then delete media
            if ($result && $snapshot->getMediaId() !== null) {
                $this->mediaStorage->delete($snapshot->getMediaId());
            }

            $pdo->commit();
            return $result;

        } catch (Throwable $e) {
            $pdo->rollBack();
            throw ApiException::internalError('Failed to delete snapshot: ' . $e->getMessage(), $e);
        }
    }

    /**
     * Get snapshot with its comments.
     *
     * @param int $id Snapshot ID
     * @return array Snapshot data with 'comments' array
     * @throws ApiException If snapshot not found
     */
    public function getWithComments(int $id): array
    {
        $snapshotData = $this->get($id);
        $comments = $this->commentRepository->findBySnapshotId($id);

        return [
            'snapshot' => $snapshotData,
            'comments' => array_map(fn($c) => $c->toArray(), $comments),
        ];
    }

    /**
     * Validate that a page is accessible (exists and parent project not deleted).
     *
     * @throws ApiException If page not accessible
     */
    private function validatePageAccessible(int $pageId): void
    {
        $page = $this->pageRepository->findById($pageId);
        if ($page === null) {
            throw ApiException::notFound('Page', $pageId);
        }

        $project = $this->projectRepository->findById($page->getProjectId());
        if ($project === null) {
            throw ApiException::badRequest('Cannot create snapshot: parent project is deleted');
        }
    }

    /**
     * Validate snapshot data.
     *
     * @throws ApiException If validation fails
     */
    private function validateSnapshotData(array $data): void
    {
        $errors = [];

        if (!isset($data['width'])) {
            $errors['width'] = 'Width is required';
        } elseif (!is_numeric($data['width']) || (int) $data['width'] <= 0) {
            $errors['width'] = 'Width must be a positive integer';
        }

        if (!isset($data['height'])) {
            $errors['height'] = 'Height is required';
        } elseif (!is_numeric($data['height']) || (int) $data['height'] <= 0) {
            $errors['height'] = 'Height must be a positive integer';
        }

        if (isset($data['captured_at'])) {
            try {
                new DateTimeImmutable($data['captured_at']);
            } catch (Throwable) {
                $errors['captured_at'] = 'Invalid date format';
            }
        }

        if (!empty($errors)) {
            throw ApiException::validationError('Validation failed', $errors);
        }
    }

    /**
     * Format snapshot for API response.
     */
    private function formatSnapshotResponse(Snapshot $snapshot): array
    {
        $data = $snapshot->toArray();

        // Add media URL
        if ($snapshot->getMediaId() !== null) {
            $data['media_url'] = $this->mediaStorage->getUrl($snapshot->getMediaId());
        } else {
            $data['media_url'] = null;
        }

        return $data;
    }

    /**
     * Create default media storage service.
     */
    private function createDefaultMediaStorage(): MediaStorageService
    {
        // Try to use the global config helper if available
        if (function_exists('createMediaStorageService')) {
            return createMediaStorageService();
        }

        // Fallback to basic local storage config
        return MediaStorageService::create([
            'storage_type' => 'local',
            'storage_path' => dirname(__DIR__, 2) . '/storage/media',
            'public_url_base' => '/storage/media',
        ]);
    }
}
