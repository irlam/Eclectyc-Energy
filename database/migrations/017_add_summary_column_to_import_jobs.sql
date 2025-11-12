-- eclectyc-energy/database/migrations/017_add_summary_column_to_import_jobs.sql
-- Add missing summary column to import_jobs table
-- This column stores the JSON summary of import results
-- Last updated: 2025-11-12

ALTER TABLE import_jobs
ADD COLUMN summary JSON NULL COMMENT 'JSON summary of import results including errors and statistics' AFTER error_message;
