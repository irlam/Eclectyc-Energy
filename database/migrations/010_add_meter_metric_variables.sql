-- eclectyc-energy/database/migrations/010_add_meter_metric_variables.sql
-- Add metric variable fields to meters table for per-metric analytics
-- Created: 09/11/2025

-- Add metric variable fields to meters table
ALTER TABLE `meters`
ADD COLUMN `metric_variable_name` VARCHAR(100) NULL COMMENT 'Name of the metric variable (e.g., "Square Meters", "Beds", "Occupancy")' AFTER `is_active`,
ADD COLUMN `metric_variable_value` DECIMAL(15, 3) NULL COMMENT 'Numeric value for the metric variable' AFTER `metric_variable_name`,
ADD INDEX `idx_metric_variable` (`metric_variable_name`);
