-- ============================================================================
-- Migration: 002_create_media_table.sql
-- Description: Creates the media table - storage abstraction for uploaded files
-- Author: Snaply Development Team
-- ============================================================================

-- Drop table if exists (for clean re-runs in development)
DROP TABLE IF EXISTS `media`;

-- Create media table
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

-- ============================================================================
-- Rollback: DROP TABLE IF EXISTS `media`;
-- ============================================================================
