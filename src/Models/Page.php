<?php

declare(strict_types=1);

namespace Snaply\Models;

use DateTime;
use InvalidArgumentException;

/**
 * Page entity representing a web page tracked within a project.
 */
class Page
{
    private ?int $id = null;
    private int $projectId;
    private string $url;
    private string $slug;
    private string $title;
    private ?string $description = null;
    private ?DateTime $deletedAt = null;
    private ?DateTime $createdAt = null;
    private ?DateTime $updatedAt = null;

    public function __construct(int $projectId, string $url, string $slug, string $title)
    {
        $this->projectId = $projectId;
        $this->setUrl($url);
        $this->setSlug($slug);
        $this->setTitle($title);
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

    public function getProjectId(): int
    {
        return $this->projectId;
    }

    public function setProjectId(int $projectId): self
    {
        $this->projectId = $projectId;
        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $url = trim($url);
        if ($url === '') {
            throw new InvalidArgumentException('Page URL cannot be empty');
        }
        if (strlen($url) > 2048) {
            throw new InvalidArgumentException('Page URL cannot exceed 2048 characters');
        }
        $this->url = $url;
        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $slug = trim($slug);
        if ($slug === '') {
            throw new InvalidArgumentException('Page slug cannot be empty');
        }
        // Validate slug format: lowercase letters, numbers, hyphens only
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw new InvalidArgumentException(
                'Page slug must contain only lowercase letters, numbers, and hyphens'
            );
        }
        $this->slug = $slug;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $title = trim($title);
        if ($title === '') {
            throw new InvalidArgumentException('Page title cannot be empty');
        }
        $this->title = $title;
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
            'project_id' => $this->projectId,
            'url' => $this->url,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
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
        $page = new self(
            (int)$data['project_id'],
            $data['url'],
            $data['slug'],
            $data['title']
        );

        if (isset($data['id'])) {
            $page->setId((int)$data['id']);
        }

        if (isset($data['description'])) {
            $page->setDescription($data['description']);
        }

        if (!empty($data['deleted_at'])) {
            $page->setDeletedAt(new DateTime($data['deleted_at']));
        }

        if (!empty($data['created_at'])) {
            $page->setCreatedAt(new DateTime($data['created_at']));
        }

        if (!empty($data['updated_at'])) {
            $page->setUpdatedAt(new DateTime($data['updated_at']));
        }

        return $page;
    }

    /**
     * Generate a URL-friendly slug from a string.
     *
     * @param string $text Input text
     * @return string URL-friendly slug
     */
    public static function generateSlug(string $text): string
    {
        // Convert to lowercase
        $slug = strtolower($text);

        // Replace non-alphanumeric characters with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        // Remove leading/trailing hyphens
        $slug = trim($slug, '-');

        // Collapse multiple hyphens
        $slug = preg_replace('/-+/', '-', $slug);

        return $slug ?: 'untitled';
    }
}
