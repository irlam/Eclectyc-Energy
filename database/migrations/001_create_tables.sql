-- eclectyc-energy/database/migrations/001_create_tables.sql
-- Initial database schema for Energy Management Platform
-- Last updated: 06/11/2024 14:45:00

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'viewer') DEFAULT 'viewer',
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suppliers table
CREATE TABLE IF NOT EXISTS suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) UNIQUE,
    contact_email VARCHAR(255),
    contact_phone VARCHAR(50),
    address TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Companies table
CREATE TABLE IF NOT EXISTS companies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    registration_number VARCHAR(50) UNIQUE,
    vat_number VARCHAR(50),
    address TEXT,
    billing_address TEXT,
    primary_contact_id INT UNSIGNED NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (primary_contact_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_registration (registration_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Regions table
CREATE TABLE IF NOT EXISTS regions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(10) UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sites table
CREATE TABLE IF NOT EXISTS sites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    region_id INT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    address TEXT NOT NULL,
    postcode VARCHAR(10),
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    site_type ENUM('office', 'warehouse', 'retail', 'industrial', 'residential', 'other') DEFAULT 'other',
    floor_area DECIMAL(10, 2) NULL COMMENT 'Square meters',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE SET NULL,
    INDEX idx_company (company_id),
    INDEX idx_region (region_id),
    INDEX idx_postcode (postcode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Meters table
CREATE TABLE IF NOT EXISTS meters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NULL,
    mpan VARCHAR(21) UNIQUE NOT NULL COMMENT 'Meter Point Administration Number',
    serial_number VARCHAR(50),
    meter_type ENUM('electricity', 'gas', 'water', 'heat') DEFAULT 'electricity',
    is_smart_meter BOOLEAN DEFAULT FALSE,
    is_half_hourly BOOLEAN DEFAULT FALSE,
    installation_date DATE NULL,
    removal_date DATE NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    INDEX idx_mpan (mpan),
    INDEX idx_site (site_id),
    INDEX idx_type (meter_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Meter readings table
CREATE TABLE IF NOT EXISTS meter_readings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meter_id INT UNSIGNED NOT NULL,
    reading_date DATE NOT NULL,
    reading_time TIME NULL,
    period_number TINYINT NULL COMMENT 'For half-hourly data: 1-48',
    reading_value DECIMAL(15, 3) NOT NULL COMMENT 'kWh or equivalent',
    reading_type ENUM('actual', 'estimated', 'manual') DEFAULT 'actual',
    is_validated BOOLEAN DEFAULT FALSE,
    import_batch_id VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meter_id) REFERENCES meters(id) ON DELETE CASCADE,
    INDEX idx_meter_date (meter_id, reading_date),
    INDEX idx_date (reading_date),
    INDEX idx_batch (import_batch_id),
    UNIQUE KEY unique_reading (meter_id, reading_date, reading_time, period_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tariffs table
CREATE TABLE IF NOT EXISTS tariffs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) UNIQUE,
    energy_type ENUM('electricity', 'gas') NOT NULL,
    tariff_type ENUM('fixed', 'variable', 'time_of_use', 'dynamic') DEFAULT 'fixed',
    unit_rate DECIMAL(10, 4) COMMENT 'Pence per kWh',
    standing_charge DECIMAL(10, 4) COMMENT 'Pence per day',
    valid_from DATE NOT NULL,
    valid_to DATE NULL,
    peak_rate DECIMAL(10, 4) NULL,
    off_peak_rate DECIMAL(10, 4) NULL,
    weekend_rate DECIMAL(10, 4) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    INDEX idx_code (code),
    INDEX idx_dates (valid_from, valid_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exports table (for data exports)
CREATE TABLE IF NOT EXISTS exports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    export_type VARCHAR(50) NOT NULL,
    export_format ENUM('csv', 'json', 'xml', 'excel') DEFAULT 'csv',
    file_name VARCHAR(255),
    file_path VARCHAR(500),
    file_size BIGINT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    error_message TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit logs table
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT UNSIGNED NULL,
    old_values JSON NULL,
    new_values JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily aggregations table (for performance)
CREATE TABLE IF NOT EXISTS daily_aggregations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meter_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    total_consumption DECIMAL(15, 3) NOT NULL,
    peak_consumption DECIMAL(15, 3) NULL,
    off_peak_consumption DECIMAL(15, 3) NULL,
    min_reading DECIMAL(15, 3) NULL,
    max_reading DECIMAL(15, 3) NULL,
    reading_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meter_id) REFERENCES meters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_daily (meter_id, date),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Weekly aggregations table
CREATE TABLE IF NOT EXISTS weekly_aggregations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meter_id INT UNSIGNED NOT NULL,
    week_start DATE NOT NULL,
    week_end DATE NOT NULL,
    total_consumption DECIMAL(15, 3) NOT NULL,
    peak_consumption DECIMAL(15, 3) NULL,
    off_peak_consumption DECIMAL(15, 3) NULL,
    min_daily_consumption DECIMAL(15, 3) NULL,
    max_daily_consumption DECIMAL(15, 3) NULL,
    day_count INT DEFAULT 0,
    reading_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meter_id) REFERENCES meters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_week (meter_id, week_start),
    INDEX idx_week (week_start, week_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monthly aggregations table
CREATE TABLE IF NOT EXISTS monthly_aggregations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meter_id INT UNSIGNED NOT NULL,
    month_start DATE NOT NULL,
    month_end DATE NOT NULL,
    total_consumption DECIMAL(15, 3) NOT NULL,
    peak_consumption DECIMAL(15, 3) NULL,
    off_peak_consumption DECIMAL(15, 3) NULL,
    min_daily_consumption DECIMAL(15, 3) NULL,
    max_daily_consumption DECIMAL(15, 3) NULL,
    day_count INT DEFAULT 0,
    reading_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meter_id) REFERENCES meters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_month (meter_id, month_start),
    INDEX idx_month (month_start, month_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Annual aggregations table
CREATE TABLE IF NOT EXISTS annual_aggregations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meter_id INT UNSIGNED NOT NULL,
    year_start DATE NOT NULL,
    year_end DATE NOT NULL,
    total_consumption DECIMAL(15, 3) NOT NULL,
    peak_consumption DECIMAL(15, 3) NULL,
    off_peak_consumption DECIMAL(15, 3) NULL,
    min_daily_consumption DECIMAL(15, 3) NULL,
    max_daily_consumption DECIMAL(15, 3) NULL,
    day_count INT DEFAULT 0,
    reading_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (meter_id) REFERENCES meters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_year (meter_id, year_start),
    INDEX idx_year (year_start, year_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings table for system configuration
CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;