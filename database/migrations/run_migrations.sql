-- ============================================================================
-- Snaply Database Migration Runner
-- ============================================================================
--
-- This script executes all migrations in the correct dependency order.
--
-- Usage:
--   mysql -u <username> -p <database_name> < run_migrations.sql
--
-- Or from MySQL client:
--   source /path/to/database/migrations/run_migrations.sql
--
-- Prerequisites:
--   - Database must already exist
--   - User must have CREATE, ALTER, DROP, INDEX, REFERENCES privileges
--
-- ============================================================================

-- Configuration
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Display migration start
SELECT '========================================' AS '';
SELECT 'Starting Snaply Database Migrations' AS '';
SELECT '========================================' AS '';

-- ============================================================================
-- Migration 001: Projects Table
-- ============================================================================
SELECT 'Running migration 001: Create projects table...' AS '';

DROP TABLE IF EXISTS `projects`;

CREATE TABLE `projects` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL COMMENT 'Project display name',
    `description` TEXT NULL COMMENT 'Optional project description',
    `status` ENUM('active', 'archived', 'completed') NOT NULL DEFAULT 'active' COMMENT 'Project lifecycle status',
    `deleted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp (NULL = active)',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation time',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last modification time',

    PRIMARY KEY (`id`),
    INDEX `idx_projects_status` (`status`),
    INDEX `idx_projects_deleted_at` (`deleted_at`),
    INDEX `idx_projects_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Top-level containers for organizing page captures';

SELECT '  -> projects table created' AS '';

-- ============================================================================
-- Migration 002: Media Table
-- ============================================================================
SELECT 'Running migration 002: Create media table...' AS '';

DROP TABLE IF EXISTS `media`;

CREATE TABLE `media` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `storage_type` VARCHAR(50) NOT NULL DEFAULT 'local' COMMENT 'Backend type: local, wordpress, s3',
    `storage_path` VARCHAR(1024) NOT NULL COMMENT 'Implementation-specific path or key',
    `original_filename` VARCHAR(255) NULL COMMENT 'Original uploaded filename',
    `mime_type` VARCHAR(100) NOT NULL COMMENT 'File MIME type (e.g., image/png)',
    `file_size` INT UNSIGNED NOT NULL COMMENT 'File size in bytes',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Upload time',

    PRIMARY KEY (`id`),
    INDEX `idx_media_storage_type` (`storage_type`),
    INDEX `idx_media_created_at` (`created_at`),
    INDEX `idx_media_mime_type` (`mime_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Storage abstraction for uploaded files (screenshots, attachments)';

SELECT '  -> media table created' AS '';

-- ============================================================================
-- Migration 003: Pages Table
-- ============================================================================
SELECT 'Running migration 003: Create pages table...' AS '';

DROP TABLE IF EXISTS `pages`;

CREATE TABLE `pages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` INT UNSIGNED NOT NULL COMMENT 'Reference to parent project',
    `url` VARCHAR(2048) NOT NULL COMMENT 'The web page URL being captured',
    `slug` VARCHAR(255) NOT NULL COMMENT 'URL-friendly identifier for routing',
    `title` VARCHAR(255) NOT NULL COMMENT 'Page display title',
    `description` TEXT NULL COMMENT 'Optional page description',
    `deleted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp (NULL = active)',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation time',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last modification time',

    PRIMARY KEY (`id`),
    INDEX `idx_pages_project_id` (`project_id`),
    INDEX `idx_pages_slug` (`slug`),
    INDEX `idx_pages_deleted_at` (`deleted_at`),
    INDEX `idx_pages_created_at` (`created_at`),
    UNIQUE INDEX `idx_pages_project_slug` (`project_id`, `slug`),

    CONSTRAINT `fk_pages_project_id`
        FOREIGN KEY (`project_id`)
        REFERENCES `projects` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Web pages being tracked within projects';

SELECT '  -> pages table created' AS '';

-- ============================================================================
-- Migration 004: Snapshots Table
-- ============================================================================
SELECT 'Running migration 004: Create snapshots table...' AS '';

DROP TABLE IF EXISTS `snapshots`;

CREATE TABLE `snapshots` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `page_id` INT UNSIGNED NOT NULL COMMENT 'Reference to parent page',
    `media_id` INT UNSIGNED NOT NULL COMMENT 'Reference to screenshot media',
    `version` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Snapshot version number',
    `width` INT UNSIGNED NOT NULL COMMENT 'Rendered width in pixels',
    `height` INT UNSIGNED NOT NULL COMMENT 'Rendered height in pixels',
    `captured_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When the snapshot was taken',
    `deleted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete timestamp (NULL = active)',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation time',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last modification time',

    PRIMARY KEY (`id`),
    INDEX `idx_snapshots_page_id` (`page_id`),
    INDEX `idx_snapshots_media_id` (`media_id`),
    INDEX `idx_snapshots_deleted_at` (`deleted_at`),
    INDEX `idx_snapshots_created_at` (`created_at`),
    INDEX `idx_snapshots_version` (`page_id`, `version`),

    CONSTRAINT `fk_snapshots_page_id`
        FOREIGN KEY (`page_id`)
        REFERENCES `pages` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT `fk_snapshots_media_id`
        FOREIGN KEY (`media_id`)
        REFERENCES `media` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Point-in-time captures of pages with associated screenshots';

SELECT '  -> snapshots table created' AS '';

-- ============================================================================
-- Migration 005: Comments Table
-- ============================================================================
SELECT 'Running migration 005: Create comments table...' AS '';

DROP TABLE IF EXISTS `comments`;

CREATE TABLE `comments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `snapshot_id` INT UNSIGNED NOT NULL COMMENT 'Reference to parent snapshot',
    `parent_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Self-reference for replies (NULL = top-level)',
    `author_name` VARCHAR(255) NOT NULL COMMENT 'Comment author display name',
    `author_email` VARCHAR(255) NULL COMMENT 'Comment author email (optional)',
    `content` TEXT NOT NULL COMMENT 'The comment text',
    `x_norm` DECIMAL(10,9) NULL DEFAULT NULL COMMENT 'Normalised X coordinate (0.0 to 1.0)',
    `y_norm` DECIMAL(10,9) NULL DEFAULT NULL COMMENT 'Normalised Y coordinate (0.0 to 1.0)',
    `is_resolved` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether comment thread is resolved',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Comment creation time',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last modification time',

    PRIMARY KEY (`id`),
    INDEX `idx_comments_snapshot_id` (`snapshot_id`),
    INDEX `idx_comments_parent_id` (`parent_id`),
    INDEX `idx_comments_created_at` (`created_at`),
    INDEX `idx_comments_is_resolved` (`is_resolved`),
    INDEX `idx_comments_coordinates` (`snapshot_id`, `x_norm`, `y_norm`),

    CONSTRAINT `fk_comments_snapshot_id`
        FOREIGN KEY (`snapshot_id`)
        REFERENCES `snapshots` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT `fk_comments_parent_id`
        FOREIGN KEY (`parent_id`)
        REFERENCES `comments` (`id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE,

    CONSTRAINT `chk_comments_x_norm`
        CHECK (`x_norm` IS NULL OR (`x_norm` >= 0 AND `x_norm` <= 1)),

    CONSTRAINT `chk_comments_y_norm`
        CHECK (`y_norm` IS NULL OR (`y_norm` >= 0 AND `y_norm` <= 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User annotations placed on snapshots with normalised coordinates';

SELECT '  -> comments table created' AS '';

-- ============================================================================
-- Re-enable foreign key checks
-- ============================================================================
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- Migration Complete
-- ============================================================================
SELECT '========================================' AS '';
SELECT 'All migrations completed successfully!' AS '';
SELECT '========================================' AS '';

-- Display table summary
SELECT 'Tables created:' AS '';
SELECT '  - projects (soft delete, status enum)' AS '';
SELECT '  - media (storage abstraction)' AS '';
SELECT '  - pages (soft delete, FK to projects)' AS '';
SELECT '  - snapshots (soft delete, width/height, FK to pages/media)' AS '';
SELECT '  - comments (normalised coordinates, FK to snapshots, self-ref)' AS '';
