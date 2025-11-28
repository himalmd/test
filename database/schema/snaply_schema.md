# Snaply Database Schema Design

## Overview

This document defines the relational database schema for Snaply, an application that allows users to capture web pages, add positioned comments, and share them with others.

## Domain Model

```
projects (1) ────< (many) pages (1) ────< (many) snapshots (1) ────< (many) comments
                                              │
                                              │ (1)
                                              ▼
                                           media

comments (1) ────< (many) comments [self-reference for threaded replies]
```

## Database Engine

- **Database**: MySQL 5.7+ / MySQL 8.0+
- **Character Set**: utf8mb4
- **Collation**: utf8mb4_unicode_ci
- **Storage Engine**: InnoDB (required for foreign key support)

---

## Table Definitions

### 1. projects

The top-level container for organizing page captures.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique identifier |
| `name` | VARCHAR(255) | NOT NULL | Project display name |
| `description` | TEXT | NULL | Optional project description |
| `status` | ENUM('active', 'archived', 'completed') | NOT NULL, DEFAULT 'active' | Project lifecycle status |
| `deleted_at` | TIMESTAMP | NULL | Soft delete timestamp (NULL = active) |
| `created_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | Last modification time |

**Indexes:**
- `PRIMARY KEY (id)`
- `INDEX idx_projects_status (status)` - Filtering by project status
- `INDEX idx_projects_deleted_at (deleted_at)` - Soft delete queries

---

### 2. pages

Individual web pages being tracked within a project.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique identifier |
| `project_id` | INT UNSIGNED | NOT NULL, FOREIGN KEY | Reference to parent project |
| `url` | VARCHAR(2048) | NOT NULL | The web page URL being captured |
| `slug` | VARCHAR(255) | NOT NULL | URL-friendly identifier for routing |
| `title` | VARCHAR(255) | NOT NULL | Page display title |
| `description` | TEXT | NULL | Optional page description |
| `deleted_at` | TIMESTAMP | NULL | Soft delete timestamp |
| `created_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | Last modification time |

**Indexes:**
- `PRIMARY KEY (id)`
- `INDEX idx_pages_project_id (project_id)` - Foreign key lookups
- `INDEX idx_pages_slug (slug)` - URL routing lookups
- `INDEX idx_pages_deleted_at (deleted_at)` - Soft delete queries
- `UNIQUE INDEX idx_pages_project_slug (project_id, slug)` - Unique slugs within a project

**Foreign Keys:**
- `FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE RESTRICT ON UPDATE CASCADE`

---

### 3. media

Storage abstraction for uploaded files (screenshots, attachments).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique identifier |
| `storage_type` | VARCHAR(50) | NOT NULL, DEFAULT 'local' | Backend type: 'local', 'wordpress', 's3' |
| `storage_path` | VARCHAR(1024) | NOT NULL | Implementation-specific path or key |
| `original_filename` | VARCHAR(255) | NULL | Original uploaded filename |
| `mime_type` | VARCHAR(100) | NOT NULL | File MIME type (e.g., image/png) |
| `file_size` | INT UNSIGNED | NOT NULL | File size in bytes |
| `created_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Upload time |

**Indexes:**
- `PRIMARY KEY (id)`
- `INDEX idx_media_storage_type (storage_type)` - Backend-specific queries
- `INDEX idx_media_created_at (created_at)` - Chronological queries, cleanup tasks

**Design Notes:**
- No `deleted_at` column: Media deletion requires actual file removal handled by the storage service
- Orphaned media cleanup is performed via scheduled application tasks
- `storage_type` enables future backend switching without schema changes

---

### 4. snapshots

Point-in-time captures of a page with associated screenshot.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique identifier |
| `page_id` | INT UNSIGNED | NOT NULL, FOREIGN KEY | Reference to parent page |
| `media_id` | INT UNSIGNED | NOT NULL, FOREIGN KEY | Reference to screenshot media |
| `version` | INT UNSIGNED | NOT NULL, DEFAULT 1 | Snapshot version number |
| `width` | INT UNSIGNED | NOT NULL | Rendered width in pixels |
| `height` | INT UNSIGNED | NOT NULL | Rendered height in pixels |
| `captured_at` | TIMESTAMP | NULL | When the snapshot was taken |
| `deleted_at` | TIMESTAMP | NULL | Soft delete timestamp |
| `created_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Record creation time |
| `updated_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | Last modification time |

**Indexes:**
- `PRIMARY KEY (id)`
- `INDEX idx_snapshots_page_id (page_id)` - Foreign key lookups
- `INDEX idx_snapshots_media_id (media_id)` - Foreign key lookups
- `INDEX idx_snapshots_deleted_at (deleted_at)` - Soft delete queries
- `INDEX idx_snapshots_created_at (created_at)` - Chronological listing

**Foreign Keys:**
- `FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE RESTRICT ON UPDATE CASCADE`
- `FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE RESTRICT ON UPDATE CASCADE`

**Design Notes:**
- `width` and `height` store the original rendered dimensions
- These dimensions are essential for converting normalised comment coordinates back to pixel positions

---

### 5. comments

User annotations placed on snapshots with normalised coordinates.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | INT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique identifier |
| `snapshot_id` | INT UNSIGNED | NOT NULL, FOREIGN KEY | Reference to parent snapshot |
| `parent_id` | INT UNSIGNED | NULL, FOREIGN KEY | Self-reference for replies (NULL = top-level) |
| `author_name` | VARCHAR(255) | NOT NULL | Comment author display name |
| `author_email` | VARCHAR(255) | NULL | Comment author email (optional) |
| `content` | TEXT | NOT NULL | The comment text |
| `x_norm` | DECIMAL(10,9) | NULL | Normalised X coordinate (0.0 to 1.0) |
| `y_norm` | DECIMAL(10,9) | NULL | Normalised Y coordinate (0.0 to 1.0) |
| `is_resolved` | TINYINT(1) | NOT NULL, DEFAULT 0 | Whether comment thread is resolved |
| `created_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Comment creation time |
| `updated_at` | TIMESTAMP | NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | Last modification time |

**Indexes:**
- `PRIMARY KEY (id)`
- `INDEX idx_comments_snapshot_id (snapshot_id)` - Foreign key lookups
- `INDEX idx_comments_parent_id (parent_id)` - Fetching replies
- `INDEX idx_comments_created_at (created_at)` - Chronological ordering
- `INDEX idx_comments_is_resolved (is_resolved)` - Filtering by resolution status

**Foreign Keys:**
- `FOREIGN KEY (snapshot_id) REFERENCES snapshots(id) ON DELETE RESTRICT ON UPDATE CASCADE`
- `FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE SET NULL ON UPDATE CASCADE`

**Design Notes:**
- Comments do NOT have soft delete - they are preserved even when parent entities are soft-deleted
- `x_norm` and `y_norm` are nullable to support page-level comments without specific positioning
- Coordinates are normalised (0.0 to 1.0) to remain valid across different viewport sizes
- `parent_id` enables threaded replies; SET NULL on delete converts replies to top-level comments

---

## Coordinate Normalisation

### Storage Format

Comment coordinates are stored as normalised values between 0.0 and 1.0:

- `x_norm = 0.0` → Left edge of snapshot
- `x_norm = 1.0` → Right edge of snapshot
- `y_norm = 0.0` → Top edge of snapshot
- `y_norm = 1.0` → Bottom edge of snapshot

### Conversion Formulas

**Storing (pixel → normalised):**
```
x_norm = pixel_x / snapshot_width
y_norm = pixel_y / snapshot_height
```

**Rendering (normalised → pixel):**
```
pixel_x = x_norm * display_width
pixel_y = y_norm * display_height
```

### Precision

Using `DECIMAL(10,9)` provides:
- Range: 0.000000000 to 9.999999999 (constrained to 0-1 by application)
- Precision: 9 decimal places
- Example: For a 1920px wide display, precision is ~0.000002 pixels

---

## Soft Delete Strategy

### Implementation Pattern

Soft deletes use a `deleted_at` TIMESTAMP column:
- `NULL` = Record is active
- `NOT NULL` = Record is soft-deleted (timestamp indicates when)

### Query Patterns

**Active records only (default for UI):**
```sql
SELECT * FROM projects WHERE deleted_at IS NULL;
```

**Including soft-deleted (admin/debug):**
```sql
SELECT * FROM projects; -- No deleted_at filter
```

**Only soft-deleted:**
```sql
SELECT * FROM projects WHERE deleted_at IS NOT NULL;
```

### Cascade Behaviour

Soft deletes do NOT cascade automatically. Application logic determines visibility:

| Scenario | Behaviour |
|----------|-----------|
| Project soft-deleted | Project hidden; pages, snapshots, comments remain but excluded from UI |
| Page soft-deleted | Page hidden; snapshots, comments remain but excluded from UI |
| Snapshot soft-deleted | Snapshot hidden; comments remain but excluded from UI |

Comments are never soft-deleted to preserve discussion history.

---

## Integrity Rules

### Database-Enforced

| Rule | Implementation |
|------|----------------|
| Foreign key relationships | FK constraints with ON DELETE RESTRICT |
| Unique slug per project | UNIQUE INDEX on (project_id, slug) |
| Required fields | NOT NULL constraints |
| Valid enum values | ENUM type on status column |

### Application-Enforced

| Rule | Rationale |
|------|-----------|
| Coordinate bounds (0.0-1.0) | CHECK constraints have limited MySQL 5.7 support; clearer validation in PHP |
| Soft delete cascading logic | Business rules determine visibility, not physical deletion |
| Media orphan cleanup | Requires file system operations beyond DB scope |
| Comment requires coordinates for pin display | Business rule - page-level comments may not need pins |
| Author email format validation | Complex validation better suited to application layer |

---

## Index Strategy

### Foreign Key Indexes

All foreign key columns have dedicated indexes for:
- JOIN performance
- Referential integrity checks
- Cascade operation efficiency

### Query-Optimised Indexes

| Index | Use Case |
|-------|----------|
| `idx_projects_status` | Filter projects by status |
| `idx_pages_slug` | URL routing lookups |
| `idx_snapshots_created_at` | Chronological snapshot listing |
| `idx_comments_is_resolved` | Filter resolved/unresolved comments |
| `idx_media_created_at` | Media cleanup and maintenance queries |
| `idx_*_deleted_at` | Soft delete filtering (all applicable tables) |

---

## Future Considerations

### User Authentication

When user authentication is implemented:
1. Add `users` table
2. Add `user_id` foreign key to `comments` table
3. Consider adding `owner_id` to `projects` table
4. Migrate existing author_name/author_email data

### Media Storage Backends

The `media.storage_type` column supports future backends:
- `local` - Local filesystem (current)
- `wordpress` - WordPress Media Library
- `s3` - Amazon S3 or compatible
- `cloudinary` - Cloudinary CDN

No schema changes required when switching backends.

### Performance Optimisation

For high-volume deployments, consider:
- Partitioning `comments` table by `snapshot_id`
- Adding composite indexes for common query patterns
- Implementing read replicas for query distribution
