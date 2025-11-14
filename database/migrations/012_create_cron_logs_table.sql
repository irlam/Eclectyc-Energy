-- Migration: Create cron_logs table
-- Date: 10/11/2025
-- Description: Add dedicated table for tracking cron job execution history
--              This provides better visibility into scheduled tasks and helps
--              with debugging and monitoring automation processes.

-- Create cron_logs table
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

-- Insert migration record
INSERT INTO `migrations` (`migration`, `batch`, `executed_at`)
VALUES ('012_create_cron_logs_table', 1, NOW())
ON DUPLICATE KEY UPDATE executed_at = NOW();
