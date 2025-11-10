-- eclectyc-energy/database/migrations/013_create_alarms_system.sql
-- Create tables for the alarms system
-- Last updated: 10/11/2025

-- Alarms table - stores alarm configurations
CREATE TABLE IF NOT EXISTS alarms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    meter_id INT UNSIGNED NULL COMMENT 'NULL means site-level alarm',
    site_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    alarm_type ENUM('consumption', 'cost') DEFAULT 'consumption',
    threshold_value DECIMAL(10, 2) NOT NULL,
    period_type ENUM('daily', 'weekly', 'monthly') DEFAULT 'daily',
    comparison_operator ENUM('greater_than', 'less_than', 'equals') DEFAULT 'greater_than',
    notification_method ENUM('email', 'dashboard', 'both') DEFAULT 'both',
    is_active BOOLEAN DEFAULT TRUE,
    last_triggered_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (meter_id) REFERENCES meters(id) ON DELETE CASCADE,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_meter (meter_id),
    INDEX idx_site (site_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alarm triggers table - stores history of when alarms were triggered
CREATE TABLE IF NOT EXISTS alarm_triggers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alarm_id INT UNSIGNED NOT NULL,
    trigger_date DATE NOT NULL,
    actual_value DECIMAL(10, 2) NOT NULL,
    threshold_value DECIMAL(10, 2) NOT NULL,
    message TEXT NULL,
    notification_sent BOOLEAN DEFAULT FALSE,
    notification_sent_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alarm_id) REFERENCES alarms(id) ON DELETE CASCADE,
    INDEX idx_alarm (alarm_id),
    INDEX idx_date (trigger_date),
    INDEX idx_notification_sent (notification_sent)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alarm recipients table - additional email recipients beyond the alarm owner
CREATE TABLE IF NOT EXISTS alarm_recipients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alarm_id INT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (alarm_id) REFERENCES alarms(id) ON DELETE CASCADE,
    INDEX idx_alarm (alarm_id),
    UNIQUE KEY unique_alarm_email (alarm_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
