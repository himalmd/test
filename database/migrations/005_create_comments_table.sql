-- ============================================================================
-- Migration: 005_create_comments_table.sql
-- Description: Creates the comments table - user annotations on snapshots
-- Author: Snaply Development Team
-- Dependencies: 004_create_snapshots_table.sql
-- ============================================================================

-- Drop table if exists (for clean re-runs in development)
DROP TABLE IF EXISTS `comments`;

-- Create comments table
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

    -- CHECK constraints for coordinate bounds (enforced in MySQL 8.0.16+)
    -- In earlier versions, these are parsed but not enforced
    CONSTRAINT `chk_comments_x_norm`
        CHECK (`x_norm` IS NULL OR (`x_norm` >= 0 AND `x_norm` <= 1)),

    CONSTRAINT `chk_comments_y_norm`
        CHECK (`y_norm` IS NULL OR (`y_norm` >= 0 AND `y_norm` <= 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User annotations placed on snapshots with normalised coordinates';

-- ============================================================================
-- Rollback: DROP TABLE IF EXISTS `comments`;
-- ============================================================================
