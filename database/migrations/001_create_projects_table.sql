-- ============================================================================
-- Migration: 001_create_projects_table.sql
-- Description: Creates the projects table - top-level container for page captures
-- Author: Snaply Development Team
-- ============================================================================

-- Drop table if exists (for clean re-runs in development)
DROP TABLE IF EXISTS `projects`;

-- Create projects table
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

-- ============================================================================
-- Rollback: DROP TABLE IF EXISTS `projects`;
-- ============================================================================
