-- ================================================================
-- FIX FOR MISSING TABLES: exports and audit_logs
-- Copy and paste this entire script into phpMyAdmin
-- Last updated: 2025-11-12
-- ================================================================

-- This script will:
-- 1. Create the exports table if it doesn't exist
-- 2. Create the audit_logs table if it doesn't exist
-- 3. Add any missing columns to existing tables
-- 4. Create all necessary indexes

-- ================================================================
-- EXPORTS TABLE
-- ================================================================

CREATE TABLE IF NOT EXISTS exports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    export_type VARCHAR(50) NOT NULL,
    export_format ENUM('csv', 'json', 'xml', 'excel') DEFAULT 'csv',
    file_name VARCHAR(255),
    file_path VARCHAR(500),
    file_size BIGINT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    error_message TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- AUDIT_LOGS TABLE
-- ================================================================

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT UNSIGNED NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    status ENUM('pending', 'completed', 'failed', 'retrying') DEFAULT 'completed',
    retry_count INT UNSIGNED DEFAULT 0,
    parent_batch_id VARCHAR(36) NULL COMMENT 'Reference to original batch if this is a retry',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at),
    INDEX idx_status (status),
    INDEX idx_parent_batch (parent_batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- ADD MISSING COLUMNS TO EXISTING TABLES (if they exist but are incomplete)
-- ================================================================

-- Add status column to audit_logs if it doesn't exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'audit_logs'
    AND COLUMN_NAME = 'status'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE audit_logs ADD COLUMN status ENUM(''pending'', ''completed'', ''failed'', ''retrying'') DEFAULT ''completed'' AFTER new_values',
    'SELECT "Column status already exists in audit_logs" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add retry_count column to audit_logs if it doesn't exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'audit_logs'
    AND COLUMN_NAME = 'retry_count'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE audit_logs ADD COLUMN retry_count INT UNSIGNED DEFAULT 0 AFTER status',
    'SELECT "Column retry_count already exists in audit_logs" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add parent_batch_id column to audit_logs if it doesn't exist
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'audit_logs'
    AND COLUMN_NAME = 'parent_batch_id'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE audit_logs ADD COLUMN parent_batch_id VARCHAR(36) NULL COMMENT ''Reference to original batch if this is a retry'' AFTER retry_count',
    'SELECT "Column parent_batch_id already exists in audit_logs" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ================================================================
-- VERIFY TABLES EXIST
-- ================================================================

-- Check if exports table exists
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ exports table exists'
        ELSE '✗ exports table MISSING'
    END AS 'Exports Table Status'
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'exports';

-- Check if audit_logs table exists
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ audit_logs table exists'
        ELSE '✗ audit_logs table MISSING'
    END AS 'Audit Logs Table Status'
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'audit_logs';

-- Show column structure of exports table
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_KEY
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'exports'
ORDER BY ORDINAL_POSITION;

-- Show column structure of audit_logs table
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_KEY
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'audit_logs'
ORDER BY ORDINAL_POSITION;

-- ================================================================
-- DONE! Tables should now be created with all necessary columns
-- ================================================================
