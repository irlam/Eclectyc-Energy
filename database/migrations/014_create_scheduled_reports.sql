-- eclectyc-energy/database/migrations/014_create_scheduled_reports.sql
-- Create tables for scheduled reports system
-- Last updated: 10/11/2025

-- Scheduled reports table - stores report configurations
CREATE TABLE IF NOT EXISTS scheduled_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    report_type ENUM('consumption', 'cost', 'data_quality', 'tariff_switching', 'custom') DEFAULT 'consumption',
    report_format ENUM('pdf', 'csv', 'excel', 'html') DEFAULT 'pdf',
    frequency ENUM('manual', 'daily', 'weekly', 'monthly') DEFAULT 'manual',
    day_of_week TINYINT NULL COMMENT 'For weekly reports: 0=Sunday, 6=Saturday',
    day_of_month TINYINT NULL COMMENT 'For monthly reports: 1-31',
    hour_of_day TINYINT DEFAULT 8 COMMENT 'Hour to send (0-23)',
    last_run_at DATETIME NULL,
    next_run_at DATETIME NULL,
    is_active BOOLEAN DEFAULT TRUE,
    filters JSON NULL COMMENT 'Store report filters as JSON',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_active (is_active),
    INDEX idx_frequency (frequency),
    INDEX idx_next_run (next_run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scheduled report recipients table
CREATE TABLE IF NOT EXISTS scheduled_report_recipients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scheduled_report_id INT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (scheduled_report_id) REFERENCES scheduled_reports(id) ON DELETE CASCADE,
    INDEX idx_report (scheduled_report_id),
    UNIQUE KEY unique_report_email (scheduled_report_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Report executions table - history of report runs
CREATE TABLE IF NOT EXISTS report_executions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scheduled_report_id INT UNSIGNED NOT NULL,
    execution_date DATETIME NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    error_message TEXT NULL,
    file_path VARCHAR(500) NULL,
    file_size INT NULL COMMENT 'File size in bytes',
    recipients_count INT DEFAULT 0,
    emails_sent INT DEFAULT 0,
    completed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (scheduled_report_id) REFERENCES scheduled_reports(id) ON DELETE CASCADE,
    INDEX idx_report (scheduled_report_id),
    INDEX idx_status (status),
    INDEX idx_execution_date (execution_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
