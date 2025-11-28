-- ============================================================================
-- Migration: 004_create_snapshots_table.sql
-- Description: Creates the snapshots table - point-in-time captures of pages
-- Author: Snaply Development Team
-- Dependencies: 003_create_pages_table.sql, 002_create_media_table.sql
-- ============================================================================

-- Drop table if exists (for clean re-runs in development)
DROP TABLE IF EXISTS `snapshots`;

-- Create snapshots table
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

-- ============================================================================
-- Rollback: DROP TABLE IF EXISTS `snapshots`;
-- ============================================================================
