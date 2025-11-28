-- ============================================================================
-- Migration: 003_create_pages_table.sql
-- Description: Creates the pages table - web pages tracked within projects
-- Author: Snaply Development Team
-- Dependencies: 001_create_projects_table.sql
-- ============================================================================

-- Drop table if exists (for clean re-runs in development)
DROP TABLE IF EXISTS `pages`;

-- Create pages table
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

-- ============================================================================
-- Rollback: DROP TABLE IF EXISTS `pages`;
-- ============================================================================
