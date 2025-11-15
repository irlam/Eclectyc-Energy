-- ============================================================================
-- FIX FOR: aggregate_annual - Column not found: 'year_start'
-- ============================================================================
-- This script fixes the annual_aggregations table schema mismatch
-- Copy and paste this entire script into phpMyAdmin and execute
-- ============================================================================

-- Step 1: Check if we have the old schema (single 'year' column)
SET @old_schema_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'annual_aggregations' 
    AND COLUMN_NAME = 'year'
);

-- Step 2: Create new table with correct schema
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

-- Step 3: Migrate existing data if old schema exists
-- This converts YEAR column to year_start (Jan 1) and year_end (Dec 31)
INSERT INTO annual_aggregations_new 
    (id, meter_id, year_start, year_end, total_consumption, peak_consumption, 
     off_peak_consumption, min_daily_consumption, max_daily_consumption, 
     day_count, reading_count, created_at, updated_at)
SELECT 
    id, 
    meter_id, 
    DATE(CONCAT(CAST(`year` AS CHAR), '-01-01')) as year_start,
    DATE(CONCAT(CAST(`year` AS CHAR), '-12-31')) as year_end,
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
WHERE @old_schema_exists > 0;

-- Step 4: Drop old table
DROP TABLE IF EXISTS annual_aggregations;

-- Step 5: Rename new table
RENAME TABLE annual_aggregations_new TO annual_aggregations;

-- ============================================================================
-- VERIFICATION: Run this query to verify the fix
-- ============================================================================
-- SELECT COLUMN_NAME, DATA_TYPE 
-- FROM information_schema.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE() 
-- AND TABLE_NAME = 'annual_aggregations'
-- ORDER BY ORDINAL_POSITION;
-- 
-- Expected columns: year_start (date), year_end (date)
-- ============================================================================
