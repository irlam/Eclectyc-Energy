-- ============================================================================
-- FIX FOR: CSV Import - Sites support
-- ============================================================================
-- This script adds 'sites' as a valid import_type
-- Copy and paste this entire script into phpMyAdmin and execute
-- ============================================================================

-- Step 1: Add 'sites' to import_jobs.import_type enum
ALTER TABLE import_jobs 
MODIFY COLUMN import_type ENUM('hh','daily','sites') 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'hh';

-- Step 2: Add 'sites' to sftp_configurations.import_type enum
ALTER TABLE sftp_configurations 
MODIFY COLUMN import_type ENUM('hh','daily','sites') 
COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hh' 
COMMENT 'Default import type for files';

-- ============================================================================
-- VERIFICATION: Run this query to verify the fix
-- ============================================================================
-- SHOW COLUMNS FROM import_jobs LIKE 'import_type';
-- SHOW COLUMNS FROM sftp_configurations LIKE 'import_type';
-- 
-- Expected: Type should show enum('hh','daily','sites')
-- ============================================================================
