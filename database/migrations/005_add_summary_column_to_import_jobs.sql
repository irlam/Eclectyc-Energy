-- eclectyc-energy/database/migrations/005_add_summary_column_to_import_jobs.sql
-- Add missing summary column to import_jobs table
-- Last updated: 2025-11-11

-- Add summary column if it doesn't exist
ALTER TABLE import_jobs 
ADD COLUMN IF NOT EXISTS `summary` json DEFAULT NULL
AFTER `metadata`;
