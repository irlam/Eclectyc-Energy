-- eclectyc-energy/database/migrations/004_create_import_jobs_table.sql
-- Create import jobs table for tracking async import processes
-- Last updated: 07/11/2025

CREATE TABLE IF NOT EXISTS import_jobs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(36) NOT NULL UNIQUE,
    user_id INT UNSIGNED NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NULL,
    import_type ENUM('hh', 'daily') NOT NULL DEFAULT 'hh',
    status ENUM('queued', 'processing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'queued',
    dry_run BOOLEAN DEFAULT FALSE,
    
    -- Progress tracking
    total_rows INT UNSIGNED NULL,
    processed_rows INT UNSIGNED DEFAULT 0,
    imported_rows INT UNSIGNED DEFAULT 0,
    failed_rows INT UNSIGNED DEFAULT 0,
    
    -- Timestamps
    queued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    
    -- Results
    error_message TEXT NULL,
    summary JSON NULL,
    
    INDEX idx_batch_id (batch_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_queued_at (queued_at),
    INDEX idx_completed_at (completed_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
