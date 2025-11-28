-- ============================================================================
-- Snaply Database Rollback Script
-- ============================================================================
--
-- WARNING: This script will DROP ALL Snaply tables and their data!
--          Use only in development/testing environments.
--
-- This script drops tables in reverse dependency order to respect
-- foreign key constraints.
--
-- Usage:
--   mysql -u <username> -p <database_name> < rollback_all.sql
--
-- ============================================================================

-- Configuration
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Display rollback start
SELECT '========================================' AS '';
SELECT 'WARNING: Rolling back ALL Snaply tables' AS '';
SELECT '========================================' AS '';

-- Drop tables in reverse dependency order
SELECT 'Dropping comments table...' AS '';
DROP TABLE IF EXISTS `comments`;

SELECT 'Dropping snapshots table...' AS '';
DROP TABLE IF EXISTS `snapshots`;

SELECT 'Dropping pages table...' AS '';
DROP TABLE IF EXISTS `pages`;

SELECT 'Dropping media table...' AS '';
DROP TABLE IF EXISTS `media`;

SELECT 'Dropping projects table...' AS '';
DROP TABLE IF EXISTS `projects`;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Display completion
SELECT '========================================' AS '';
SELECT 'All Snaply tables have been dropped' AS '';
SELECT '========================================' AS '';
