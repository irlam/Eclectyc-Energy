-- Migration: Add batch_id columns for import tracking
-- Created: 2025-11-08
-- Purpose: Add batch_id columns to meters and meter_readings tables to track which import created them

-- Add batch_id to meters table (for auto-created meters)
ALTER TABLE `meters` 
ADD COLUMN `batch_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Batch ID of import that created this meter' AFTER `updated_at`,
ADD INDEX `idx_batch_id` (`batch_id`);

-- Add batch_id to meter_readings table (for tracking readings by import)
ALTER TABLE `meter_readings` 
ADD COLUMN `batch_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Batch ID of import that created this reading' AFTER `created_at`,
ADD INDEX `idx_batch_id` (`batch_id`);

-- Add optional foreign key constraint (commented out as import_jobs might not exist yet)
-- This ensures data integrity if import_jobs table exists
-- ALTER TABLE `meters` 
-- ADD CONSTRAINT `fk_meters_batch_id` 
-- FOREIGN KEY (`batch_id`) REFERENCES `import_jobs` (`batch_id`) 
-- ON DELETE SET NULL;

-- ALTER TABLE `meter_readings` 
-- ADD CONSTRAINT `fk_meter_readings_batch_id` 
-- FOREIGN KEY (`batch_id`) REFERENCES `import_jobs` (`batch_id`) 
-- ON DELETE SET NULL;
