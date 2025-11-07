-- Add status tracking columns to audit_logs table
-- This migration adds columns for batch processing status tracking

ALTER TABLE audit_logs
ADD COLUMN status ENUM('pending', 'completed', 'failed', 'retrying') DEFAULT 'completed' AFTER new_values;

ALTER TABLE audit_logs
ADD COLUMN retry_count INT UNSIGNED DEFAULT 0 AFTER status;

ALTER TABLE audit_logs
ADD COLUMN parent_batch_id VARCHAR(36) NULL COMMENT 'Reference to original batch if this is a retry' AFTER retry_count;

ALTER TABLE audit_logs
ADD INDEX idx_status (status);

ALTER TABLE audit_logs
ADD INDEX idx_parent_batch (parent_batch_id);