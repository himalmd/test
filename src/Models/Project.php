<?php

declare(strict_types=1);

namespace Snaply\Models;

use DateTime;
use InvalidArgumentException;

/**
 * Project entity representing a top-level container for page captures.
 */
class Project
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_COMPLETED = 'completed';

    public const VALID_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
        self::STATUS_COMPLETED,
    ];

    private ?int $id = null;
    private string $name;
    private ?string $description = null;
    private string $status = self::STATUS_ACTIVE;
    private ?DateTime $deletedAt = null;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;

    public function __construct(string $name, ?string $description = null)
    {
        $this->setName($name);
        $this->description = $description;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('Project name cannot be empty');
        }
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                sprintf('Invalid status "%s". Must be one of: %s', $status, implode(', ', self::VALID_STATUSES))
            );
        }
        $this->status = $status;
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
     * Convert entity to associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
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
        $project = new self($data['name'], $data['description'] ?? null);

        if (isset($data['id'])) {
            $project->setId((int)$data['id']);
        }

        if (isset($data['status'])) {
            $project->setStatus($data['status']);
        }

        if (!empty($data['deleted_at'])) {
            $project->setDeletedAt(new DateTime($data['deleted_at']));
        }

        if (!empty($data['created_at'])) {
            $project->setCreatedAt(new DateTime($data['created_at']));
        }

        if (!empty($data['updated_at'])) {
            $project->setUpdatedAt(new DateTime($data['updated_at']));
        }

        return $project;
    }
}
