-- SQL Fix for Missing 'summary' Column in import_jobs Table
-- Copy and paste this into phpMyAdmin to fix the error:
-- SQLSTATE[42S22]: Column not found: 1054 Unknown column 'summary' in 'field list'

-- Add the missing summary column to import_jobs table
ALTER TABLE `import_jobs`
ADD COLUMN `summary` JSON NULL COMMENT 'JSON summary of import results including errors and statistics' AFTER `error_message`;
