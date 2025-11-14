-- Add status tracking columns to audit_logs table (single statement version)
-- This migration adds columns for batch processing status tracking

ALTER TABLE audit_logs
ADD COLUMN status ENUM('pending', 'completed', 'failed', 'retrying') DEFAULT 'completed' AFTER new_values,
ADD COLUMN retry_count INT UNSIGNED DEFAULT 0 AFTER status,
ADD COLUMN parent_batch_id VARCHAR(36) NULL AFTER retry_count COMMENT 'Reference to original batch if this is a retry',
ADD INDEX idx_status (status),
ADD INDEX idx_parent_batch (parent_batch_id);