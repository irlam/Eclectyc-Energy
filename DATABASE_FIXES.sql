-- ============================================================================
-- Eclectyc Energy Database Fixes
-- Apply these changes to fix database connection issues and add cron logging
-- 
-- Instructions:
-- 1. Open phpMyAdmin
-- 2. Select your database (k87747_eclectyc or your database name)
-- 3. Go to the SQL tab
-- 4. Copy and paste this entire file
-- 5. Click "Go" to execute
--
-- Date: 10/11/2025
-- Version: 1.0
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Step 1: Create cron_logs table for better visibility of scheduled jobs
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `cron_logs` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `job_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Name/type of cron job',
  `job_type` enum('daily','weekly','monthly','annual','import','export','cleanup','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'other',
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `duration_seconds` int DEFAULT NULL,
  `status` enum('running','completed','failed','timeout') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'running',
  `exit_code` int DEFAULT NULL,
  `records_processed` int DEFAULT '0',
  `records_failed` int DEFAULT '0',
  `errors_count` int DEFAULT '0',
  `warnings_count` int DEFAULT '0',
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `log_data` json DEFAULT NULL COMMENT 'Additional structured log data',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_job_name` (`job_name`),
  KEY `idx_job_type` (`job_type`),
  KEY `idx_status` (`status`),
  KEY `idx_start_time` (`start_time`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks cron job execution history for monitoring and debugging';

-- ----------------------------------------------------------------------------
-- Step 2: Add migration record
-- ----------------------------------------------------------------------------

INSERT INTO `migrations` (`migration`, `batch`, `executed_at`)
VALUES ('012_create_cron_logs_table', 1, NOW())
ON DUPLICATE KEY UPDATE executed_at = NOW();

-- ----------------------------------------------------------------------------
-- Step 3: Verify all required tables exist
-- ----------------------------------------------------------------------------

-- Check that critical tables exist (these should already be present)
-- If any are missing, you need to import the full database.sql file

SELECT 
    CASE 
        WHEN COUNT(*) = 31 THEN '✓ All required tables exist'
        ELSE CONCAT('⚠ WARNING: Only ', COUNT(*), ' of 31 required tables found. Please import database/database.sql')
    END AS table_check_status,
    COUNT(*) as tables_found
FROM information_schema.tables 
WHERE table_schema = DATABASE()
AND table_name IN (
    'ai_insights', 'annual_aggregations', 'audit_logs', 'companies', 
    'comparison_snapshots', 'cron_logs', 'daily_aggregations', 'data_quality_issues',
    'exports', 'external_calorific_values', 'external_carbon_intensity', 
    'external_temperature_data', 'import_jobs', 'meters', 'meter_readings',
    'migrations', 'monthly_aggregations', 'permissions', 'regions',
    'scheduler_alerts', 'scheduler_executions', 'settings', 'sftp_configurations',
    'sites', 'suppliers', 'system_settings', 'tariffs', 'tariff_switching_analyses',
    'users', 'user_company_access', 'user_permissions', 'user_region_access',
    'user_site_access', 'weekly_aggregations'
);

-- ----------------------------------------------------------------------------
-- Step 4: View recent cron logs (if any exist)
-- ----------------------------------------------------------------------------

SELECT 
    'Recent Cron Logs (Last 10)' as info_message,
    COALESCE(COUNT(*), 0) as total_logs
FROM `cron_logs`;

-- Show last 10 entries if table has data
SELECT 
    job_name,
    job_type,
    start_time,
    duration_seconds,
    status,
    records_processed,
    errors_count,
    created_at
FROM `cron_logs`
ORDER BY created_at DESC
LIMIT 10;

-- ----------------------------------------------------------------------------
-- Step 5: View recent audit logs to confirm system is working
-- ----------------------------------------------------------------------------

SELECT 
    'Recent Audit Logs (Last 10)' as info_message,
    COUNT(*) as total_logs
FROM `audit_logs`;

SELECT 
    action,
    entity_type,
    created_at,
    JSON_EXTRACT(new_values, '$.total_meters') as total_meters,
    JSON_EXTRACT(new_values, '$.errors') as errors
FROM `audit_logs`
WHERE action IN ('daily_aggregation', 'weekly_aggregation', 'monthly_aggregation', 'import_csv')
ORDER BY created_at DESC
LIMIT 10;

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- Run these queries to verify everything is working correctly:

-- 1. Check cron_logs table structure
DESCRIBE `cron_logs`;

-- 2. Count total cron log entries
SELECT COUNT(*) as total_cron_logs FROM `cron_logs`;

-- 3. Check for failed jobs in last 7 days
SELECT 
    job_name,
    start_time,
    error_message
FROM `cron_logs`
WHERE status = 'failed'
  AND start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY start_time DESC;

-- 4. Job execution summary
SELECT 
    job_type,
    COUNT(*) as executions,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
FROM `cron_logs`
GROUP BY job_type;

-- ============================================================================
-- COMPLETION MESSAGE
-- ============================================================================

SELECT 
    '✓ Database fixes applied successfully!' as status,
    'You can now:' as next_steps,
    '1. Check system health at: https://eclectyc.energy/tools/system-health' as step_1,
    '2. View cron logs at: https://eclectyc.energy/tools/logs' as step_2,
    '3. Read documentation at: https://eclectyc.energy/tools/docs/CRON_LOGGING.md' as step_3;

-- ============================================================================
-- OPTIONAL: Clean up old logs (uncomment if needed)
-- ============================================================================

-- Uncomment the following to delete cron logs older than 30 days:
-- DELETE FROM `cron_logs` WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Uncomment the following to archive old audit logs (older than 90 days):
-- This is recommended if your audit_logs table is very large
-- DELETE FROM `audit_logs` WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY) LIMIT 5000;

-- ============================================================================
