-- Migration: Fix annual_aggregations table schema
-- Changes `year` column to `year_start` and `year_end` DATE columns
-- This aligns with the expected schema in PeriodAggregator.php
-- Created: 2025-11-15

-- First, check if the old schema exists (single year column)
-- If yes, we'll convert it; if no, we'll create the correct schema

-- Create a temporary table with the new schema
CREATE TABLE IF NOT EXISTS annual_aggregations_new (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meter_id INT UNSIGNED NOT NULL,
    year_start DATE NOT NULL,
    year_end DATE NOT NULL,
    total_consumption DECIMAL(15, 3) NOT NULL,
    peak_consumption DECIMAL(15, 3) NULL,
    off_peak_consumption DECIMAL(15, 3) NULL,
    min_daily_consumption DECIMAL(15, 3) NULL,
    max_daily_consumption DECIMAL(15, 3) NULL,
    day_count INT DEFAULT 0,
    reading_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meter_id) REFERENCES meters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_year (meter_id, year_start),
    INDEX idx_year (year_start, year_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate data if old table exists with old schema
-- Convert year (YEAR type) to year_start and year_end (DATE)
INSERT INTO annual_aggregations_new 
    (id, meter_id, year_start, year_end, total_consumption, peak_consumption, 
     off_peak_consumption, min_daily_consumption, max_daily_consumption, 
     day_count, reading_count, created_at, updated_at)
SELECT 
    id, 
    meter_id, 
    CONCAT(CAST(year AS CHAR), '-01-01') as year_start,
    CONCAT(CAST(year AS CHAR), '-12-31') as year_end,
    total_consumption,
    peak_consumption,
    off_peak_consumption,
    min_daily_consumption,
    max_daily_consumption,
    day_count,
    reading_count,
    created_at,
    updated_at
FROM annual_aggregations
WHERE EXISTS (
    SELECT 1 FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'annual_aggregations' 
    AND COLUMN_NAME = 'year'
);

-- Drop the old table
DROP TABLE IF EXISTS annual_aggregations;

-- Rename the new table
RENAME TABLE annual_aggregations_new TO annual_aggregations;
