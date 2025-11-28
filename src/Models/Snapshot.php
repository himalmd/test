<?php

declare(strict_types=1);

namespace Snaply\Models;

use DateTime;
use InvalidArgumentException;

/**
 * Snapshot entity representing a point-in-time capture of a page.
 *
 * Each snapshot has associated dimensions (width/height) that are essential
 * for converting normalised comment coordinates back to pixel positions.
 */
class Snapshot
{
    private ?int $id = null;
    private int $pageId;
    private int $mediaId;
    private int $version = 1;
    private int $width;
    private int $height;
    private ?DateTime $capturedAt = null;
    private ?DateTime $deletedAt = null;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;

    public function __construct(int $pageId, int $mediaId, int $width, int $height)
    {
        $this->pageId = $pageId;
        $this->mediaId = $mediaId;
        $this->setWidth($width);
        $this->setHeight($height);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getPageId(): int
    {
        return $this->pageId;
    }

    public function setPageId(int $pageId): self
    {
        $this->pageId = $pageId;
        return $this;
    }

    public function getMediaId(): int
    {
        return $this->mediaId;
    }

    public function setMediaId(int $mediaId): self
    {
        $this->mediaId = $mediaId;
        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): self
    {
        if ($version < 1) {
            throw new InvalidArgumentException('Version must be at least 1');
        }
        $this->version = $version;
        return $this;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): self
    {
        if ($width <= 0) {
            throw new InvalidArgumentException('Width must be a positive integer');
        }
        $this->width = $width;
        return $this;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): self
    {
        if ($height <= 0) {
            throw new InvalidArgumentException('Height must be a positive integer');
        }
        $this->height = $height;
        return $this;
    }

    public function getCapturedAt(): ?DateTime
    {
        return $this->capturedAt;
    }

    public function setCapturedAt(?DateTime $capturedAt): self
    {
        $this->capturedAt = $capturedAt;
        return $this;
    }

    public function getDeletedAt(): ?DateTime
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTime $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function getCreatedAt(): ?DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Get the aspect ratio of this snapshot.
     *
     * @return float Width divided by height
     */
    public function getAspectRatio(): float
    {
        return $this->width / $this->height;
    }

    /**
     * Convert normalised coordinates to pixel coordinates for this snapshot.
     *
     * @param float $xNorm Normalised X coordinate (0.0 to 1.0)
     * @param float $yNorm Normalised Y coordinate (0.0 to 1.0)
     * @return array{x: int, y: int} Pixel coordinates
     */
    public function normalisedToPixels(float $xNorm, float $yNorm): array
    {
        return [
            'x' => (int)round($xNorm * $this->width),
            'y' => (int)round($yNorm * $this->height),
        ];
    }

    /**
     * Convert pixel coordinates to normalised coordinates for this snapshot.
     *
     * @param int $pixelX Pixel X coordinate
     * @param int $pixelY Pixel Y coordinate
     * @return array{x_norm: float, y_norm: float} Normalised coordinates
     */
    public function pixelsToNormalised(int $pixelX, int $pixelY): array
    {
        return [
            'x_norm' => $pixelX / $this->width,
            'y_norm' => $pixelY / $this->height,
        ];
    }

    /**
     * Convert entity to associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'page_id' => $this->pageId,
            'media_id' => $this->mediaId,
            'version' => $this->version,
            'width' => $this->width,
            'height' => $this->height,
            'captured_at' => $this->capturedAt?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deletedAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Create entity from database row.
     *
     * @param array<string, mixed> $data Database row
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $snapshot = new self(
            (int)$data['page_id'],
            (int)$data['media_id'],
            (int)$data['width'],
            (int)$data['height']
        );

        if (isset($data['id'])) {
            $snapshot->setId((int)$data['id']);
        }

        if (isset($data['version'])) {
            $snapshot->setVersion((int)$data['version']);
        }

        if (!empty($data['captured_at'])) {
            $snapshot->setCapturedAt(new DateTime($data['captured_at']));
        }

        if (!empty($data['deleted_at'])) {
            $snapshot->setDeletedAt(new DateTime($data['deleted_at']));
        }

        if (!empty($data['created_at'])) {
            $snapshot->setCreatedAt(new DateTime($data['created_at']));
        }

        if (!empty($data['updated_at'])) {
            $snapshot->setUpdatedAt(new DateTime($data['updated_at']));
        }

        return $snapshot;
    }
}
