<?php

declare(strict_types=1);

namespace Snaply\Models;

use DateTime;
use InvalidArgumentException;

/**
 * Comment entity representing a user annotation on a snapshot.
 *
 * Comments store normalised coordinates (0.0 to 1.0) that remain valid
 * across different viewport sizes. The front-end converts these to pixel
 * positions using the snapshot's dimensions.
 */
class Comment
{
    private ?int $id = null;
    private int $snapshotId;
    private ?int $parentId = null;
    private string $authorName;
    private ?string $authorEmail = null;
    private string $content;
    private ?float $xNorm = null;
    private ?float $yNorm = null;
    private bool $isResolved = false;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;

    public function __construct(int $snapshotId, string $authorName, string $content)
    {
        $this->snapshotId = $snapshotId;
        $this->setAuthorName($authorName);
        $this->setContent($content);
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

    public function getSnapshotId(): int
    {
        return $this->snapshotId;
    }

    public function setSnapshotId(int $snapshotId): self
    {
        $this->snapshotId = $snapshotId;
        return $this;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function setParentId(?int $parentId): self
    {
        $this->parentId = $parentId;
        return $this;
    }

    public function isReply(): bool
    {
        return $this->parentId !== null;
    }

    public function getAuthorName(): string
    {
        return $this->authorName;
    }

    public function setAuthorName(string $authorName): self
    {
        $authorName = trim($authorName);
        if ($authorName === '') {
            throw new InvalidArgumentException('Author name cannot be empty');
        }
        $this->authorName = $authorName;
        return $this;
    }

    public function getAuthorEmail(): ?string
    {
        return $this->authorEmail;
    }

    public function setAuthorEmail(?string $authorEmail): self
    {
        if ($authorEmail !== null) {
            $authorEmail = trim($authorEmail);
            if ($authorEmail !== '' && !filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Invalid email address format');
            }
            $authorEmail = $authorEmail === '' ? null : $authorEmail;
        }
        $this->authorEmail = $authorEmail;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $content = trim($content);
        if ($content === '') {
            throw new InvalidArgumentException('Comment content cannot be empty');
        }
        $this->content = $content;
        return $this;
    }

    public function getXNorm(): ?float
    {
        return $this->xNorm;
    }

    public function setXNorm(?float $xNorm): self
    {
        if ($xNorm !== null && ($xNorm < 0.0 || $xNorm > 1.0)) {
            throw new InvalidArgumentException(
                'X coordinate must be normalised between 0.0 and 1.0'
            );
        }
        $this->xNorm = $xNorm;
        return $this;
    }

    public function getYNorm(): ?float
    {
        return $this->yNorm;
    }

    public function setYNorm(?float $yNorm): self
    {
        if ($yNorm !== null && ($yNorm < 0.0 || $yNorm > 1.0)) {
            throw new InvalidArgumentException(
                'Y coordinate must be normalised between 0.0 and 1.0'
            );
        }
        $this->yNorm = $yNorm;
        return $this;
    }

    /**
     * Set both coordinates at once.
     *
     * @param float|null $xNorm Normalised X coordinate (0.0 to 1.0) or null
     * @param float|null $yNorm Normalised Y coordinate (0.0 to 1.0) or null
     * @return self
     */
    public function setCoordinates(?float $xNorm, ?float $yNorm): self
    {
        $this->setXNorm($xNorm);
        $this->setYNorm($yNorm);
        return $this;
    }

    /**
     * Check if this comment has position coordinates.
     *
     * @return bool True if both x_norm and y_norm are set
     */
    public function hasCoordinates(): bool
    {
        return $this->xNorm !== null && $this->yNorm !== null;
    }

    public function isResolved(): bool
    {
        return $this->isResolved;
    }

    public function setIsResolved(bool $isResolved): self
    {
        $this->isResolved = $isResolved;
        return $this;
    }

    public function resolve(): self
    {
        $this->isResolved = true;
        return $this;
    }

    public function unresolve(): self
    {
        $this->isResolved = false;
        return $this;
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
     * Convert entity to associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'snapshot_id' => $this->snapshotId,
            'parent_id' => $this->parentId,
            'author_name' => $this->authorName,
            'author_email' => $this->authorEmail,
            'content' => $this->content,
            'x_norm' => $this->xNorm,
            'y_norm' => $this->yNorm,
            'is_resolved' => $this->isResolved,
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
        $comment = new self(
            (int)$data['snapshot_id'],
            $data['author_name'],
            $data['content']
        );

        if (isset($data['id'])) {
            $comment->setId((int)$data['id']);
        }

        if (isset($data['parent_id']) && $data['parent_id'] !== null) {
            $comment->setParentId((int)$data['parent_id']);
        }

        if (isset($data['author_email'])) {
            $comment->setAuthorEmail($data['author_email']);
        }

        if (isset($data['x_norm']) && $data['x_norm'] !== null) {
            $comment->setXNorm((float)$data['x_norm']);
        }

        if (isset($data['y_norm']) && $data['y_norm'] !== null) {
            $comment->setYNorm((float)$data['y_norm']);
        }

        if (isset($data['is_resolved'])) {
            $comment->setIsResolved((bool)$data['is_resolved']);
        }

        if (!empty($data['created_at'])) {
            $comment->setCreatedAt(new DateTime($data['created_at']));
        }

        if (!empty($data['updated_at'])) {
            $comment->setUpdatedAt(new DateTime($data['updated_at']));
        }

        return $comment;
    }
}
