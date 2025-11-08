-- eclectyc-energy/database/migrations/008_create_sftp_configurations.sql
-- Create SFTP configurations table for managing remote file connections
-- Last updated: 2025-11-08

CREATE TABLE IF NOT EXISTS sftp_configurations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT 'Friendly name for the SFTP connection',
    host VARCHAR(255) NOT NULL COMMENT 'SFTP server hostname or IP',
    port INT UNSIGNED DEFAULT 22 COMMENT 'SFTP port (default 22)',
    username VARCHAR(255) NOT NULL COMMENT 'SFTP username',
    password VARCHAR(500) NULL COMMENT 'Encrypted SFTP password',
    private_key_path VARCHAR(500) NULL COMMENT 'Path to SSH private key file',
    remote_directory VARCHAR(500) DEFAULT '/' COMMENT 'Remote directory to monitor',
    file_pattern VARCHAR(255) DEFAULT '*.csv' COMMENT 'File pattern to match (e.g., *.csv, data_*.csv)',
    import_type ENUM('hh', 'daily') NOT NULL DEFAULT 'hh' COMMENT 'Default import type for files',
    auto_import BOOLEAN DEFAULT FALSE COMMENT 'Automatically import matching files',
    delete_after_import BOOLEAN DEFAULT FALSE COMMENT 'Delete files from SFTP after successful import',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Whether this configuration is active',
    last_connection_at TIMESTAMP NULL COMMENT 'Last successful connection timestamp',
    last_error TEXT NULL COMMENT 'Last connection error message',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_name (name),
    INDEX idx_is_active (is_active),
    INDEX idx_auto_import (auto_import)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add import throttle settings to system configurations
CREATE TABLE IF NOT EXISTS system_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE COMMENT 'Setting identifier',
    setting_value TEXT NULL COMMENT 'Setting value',
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string' COMMENT 'Value type',
    description TEXT NULL COMMENT 'Setting description',
    is_editable BOOLEAN DEFAULT TRUE COMMENT 'Can be edited via UI',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default import throttle settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_editable) VALUES
('import_throttle_enabled', 'false', 'boolean', 'Enable import throttling to prevent server overload', true),
('import_throttle_batch_size', '100', 'integer', 'Number of rows to process before throttling pause', true),
('import_throttle_delay_ms', '100', 'integer', 'Delay in milliseconds between batches', true),
('import_max_execution_time', '300', 'integer', 'Maximum execution time for imports in seconds', true),
('import_max_memory_mb', '256', 'integer', 'Maximum memory allocation for imports in MB', true)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;
