-- Migration: Add default site and tariff columns to import_jobs
-- Created: 2025-11-08
-- Purpose: Add default_site_id and default_tariff_id to import_jobs table for auto-assignment during import

-- Add default_site_id and default_tariff_id to import_jobs table
ALTER TABLE `import_jobs` 
ADD COLUMN `default_site_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Default site to assign to auto-created meters' AFTER `parent_job_id`,
ADD COLUMN `default_tariff_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Default tariff to assign to meters' AFTER `default_site_id`,
ADD INDEX `idx_default_site` (`default_site_id`),
ADD INDEX `idx_default_tariff` (`default_tariff_id`);

-- Add foreign key constraints (optional, ensures data integrity)
ALTER TABLE `import_jobs` 
ADD CONSTRAINT `fk_import_jobs_site` 
FOREIGN KEY (`default_site_id`) REFERENCES `sites` (`id`) 
ON DELETE SET NULL;

ALTER TABLE `import_jobs` 
ADD CONSTRAINT `fk_import_jobs_tariff` 
FOREIGN KEY (`default_tariff_id`) REFERENCES `tariffs` (`id`) 
ON DELETE SET NULL;
