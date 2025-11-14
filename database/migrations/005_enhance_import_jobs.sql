-- eclectyc-energy/database/migrations/005_enhance_import_jobs.sql
-- Enhance import jobs table with retry logic and richer attribution
-- Last updated: 2025-11-07

-- Add retry mechanism fields
ALTER TABLE import_jobs
ADD COLUMN retry_count INT UNSIGNED DEFAULT 0 COMMENT 'Number of times this job has been retried',
ADD COLUMN max_retries INT UNSIGNED DEFAULT 3 COMMENT 'Maximum number of retries allowed',
ADD COLUMN retry_at TIMESTAMP NULL COMMENT 'When to retry this job (for delayed retries)',
ADD COLUMN last_error TEXT NULL COMMENT 'Last error message (preserved across retries)';

-- Add batch attribution and metadata fields
ALTER TABLE import_jobs
ADD COLUMN notes TEXT NULL COMMENT 'User notes about this import',
ADD COLUMN priority ENUM('low', 'normal', 'high') DEFAULT 'normal' COMMENT 'Job priority',
ADD COLUMN tags JSON NULL COMMENT 'Custom tags for categorization',
ADD COLUMN metadata JSON NULL COMMENT 'Additional metadata (source, schedule info, etc)';

-- Add monitoring and alerting fields
ALTER TABLE import_jobs
ADD COLUMN alert_sent BOOLEAN DEFAULT FALSE COMMENT 'Whether failure alert has been sent',
ADD COLUMN alert_sent_at TIMESTAMP NULL COMMENT 'When the alert was sent';

-- Add indexes for retry and monitoring queries
CREATE INDEX idx_retry_at ON import_jobs(retry_at);
CREATE INDEX idx_priority ON import_jobs(priority, queued_at);
CREATE INDEX idx_alert_sent ON import_jobs(alert_sent, status);
