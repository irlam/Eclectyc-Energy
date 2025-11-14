-- eclectyc-energy/database/migrations/002_add_import_batch_tracking.sql
-- Add batch status tracking for import retries
-- Last updated: 06/11/2025

-- Add status and retry tracking to audit_logs for import batches
-- Note: Default 'completed' is correct for existing records being altered
ALTER TABLE audit_logs
ADD COLUMN status ENUM('pending', 'completed', 'failed', 'retrying') DEFAULT 'completed' AFTER new_values,
ADD COLUMN retry_count INT UNSIGNED DEFAULT 0 AFTER status,
ADD COLUMN parent_batch_id VARCHAR(36) NULL AFTER retry_count COMMENT 'Reference to original batch if this is a retry',
ADD INDEX idx_status (status),
ADD INDEX idx_parent_batch (parent_batch_id);
