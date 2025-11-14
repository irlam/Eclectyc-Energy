-- eclectyc-energy/database/migrations/003_add_analytics_tables.sql
-- Database schema for data aggregation & analytics features
-- Last updated: 07/11/2025

-- Scheduler execution tracking table
CREATE TABLE IF NOT EXISTS scheduler_executions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    execution_id VARCHAR(50) UNIQUE NOT NULL,
    range_type ENUM('daily', 'weekly', 'monthly', 'annual') NOT NULL,
    target_date DATE NOT NULL,
    status ENUM('running', 'completed', 'failed') DEFAULT 'running',
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    duration_seconds DECIMAL(10, 3) NULL,
    meters_processed INT DEFAULT 0,
    error_count INT DEFAULT 0,
    warning_count INT DEFAULT 0,
    error_message TEXT NULL,
    telemetry_data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_execution_id (execution_id),
    INDEX idx_range_type (range_type),
    INDEX idx_status (status),
    INDEX idx_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scheduler alerts table
CREATE TABLE IF NOT EXISTS scheduler_alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alert_type ENUM('failure', 'warning', 'summary') NOT NULL,
    range_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    is_acknowledged BOOLEAN DEFAULT FALSE,
    acknowledged_by INT UNSIGNED NULL,
    acknowledged_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_alert_type (alert_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- External temperature data table
CREATE TABLE IF NOT EXISTS external_temperature_data (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    avg_temperature DECIMAL(5, 2) NULL COMMENT 'Celsius',
    min_temperature DECIMAL(5, 2) NULL COMMENT 'Celsius',
    max_temperature DECIMAL(5, 2) NULL COMMENT 'Celsius',
    source VARCHAR(100) DEFAULT 'manual',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_location_date (location, date),
    INDEX idx_location (location),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- External calorific values table (for gas)
CREATE TABLE IF NOT EXISTS external_calorific_values (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    region VARCHAR(100) NOT NULL,
    date DATE NOT NULL,
    calorific_value DECIMAL(10, 4) NOT NULL COMMENT 'Energy content',
    unit VARCHAR(20) DEFAULT 'MJ/m3',
    source VARCHAR(100) DEFAULT 'manual',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_region_date (region, date),
    INDEX idx_region (region),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- External carbon intensity data table
CREATE TABLE IF NOT EXISTS external_carbon_intensity (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    region VARCHAR(100) NOT NULL,
    datetime DATETIME NOT NULL,
    intensity DECIMAL(10, 2) NOT NULL COMMENT 'gCO2/kWh',
    forecast DECIMAL(10, 2) NULL COMMENT 'Forecasted intensity',
    actual DECIMAL(10, 2) NULL COMMENT 'Actual intensity',
    source VARCHAR(100) DEFAULT 'manual',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_region_datetime (region, datetime),
    INDEX idx_region (region),
    INDEX idx_datetime (datetime)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data quality issues tracking table
CREATE TABLE IF NOT EXISTS data_quality_issues (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meter_id INT UNSIGNED NOT NULL,
    issue_date DATE NOT NULL,
    issue_type ENUM('missing_data', 'anomaly', 'outlier', 'negative_value', 'zero_reading') NOT NULL,
    severity ENUM('low', 'medium', 'high') DEFAULT 'medium',
    description TEXT NULL,
    issue_data JSON NULL,
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_at DATETIME NULL,
    resolved_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meter_id) REFERENCES meters(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_meter (meter_id),
    INDEX idx_date (issue_date),
    INDEX idx_type (issue_type),
    INDEX idx_resolved (is_resolved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comparison snapshots cache table
CREATE TABLE IF NOT EXISTS comparison_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meter_id INT UNSIGNED NOT NULL,
    snapshot_date DATE NOT NULL,
    snapshot_type ENUM('daily', 'weekly', 'monthly', 'annual') NOT NULL,
    current_data JSON NOT NULL,
    comparison_data JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meter_id) REFERENCES meters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_snapshot (meter_id, snapshot_date, snapshot_type),
    INDEX idx_meter (meter_id),
    INDEX idx_date (snapshot_date),
    INDEX idx_type (snapshot_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AI insights cache table
CREATE TABLE IF NOT EXISTS ai_insights (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meter_id INT UNSIGNED NOT NULL,
    insight_date DATE NOT NULL,
    insight_type VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    recommendations JSON NULL,
    confidence_score DECIMAL(5, 2) NULL COMMENT 'Percentage 0-100',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    is_dismissed BOOLEAN DEFAULT FALSE,
    dismissed_by INT UNSIGNED NULL,
    dismissed_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meter_id) REFERENCES meters(id) ON DELETE CASCADE,
    FOREIGN KEY (dismissed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_meter (meter_id),
    INDEX idx_date (insight_date),
    INDEX idx_type (insight_type),
    INDEX idx_dismissed (is_dismissed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
