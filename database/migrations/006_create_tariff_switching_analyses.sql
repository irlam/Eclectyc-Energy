-- eclectyc-energy/database/migrations/006_create_tariff_switching_analyses.sql
-- Tariff switching analysis tracking table
-- Last updated: 07/11/2025

-- Tariff switching analyses table
CREATE TABLE IF NOT EXISTS tariff_switching_analyses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    meter_id INT UNSIGNED NOT NULL,
    current_tariff_id INT UNSIGNED NULL,
    recommended_tariff_id INT UNSIGNED NULL,
    analysis_start_date DATE NOT NULL,
    analysis_end_date DATE NOT NULL,
    current_cost DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Total cost with current tariff',
    recommended_cost DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Total cost with recommended tariff',
    potential_savings DECIMAL(12, 2) NOT NULL DEFAULT 0.00 COMMENT 'Potential savings amount',
    savings_percent DECIMAL(5, 2) NOT NULL DEFAULT 0.00 COMMENT 'Potential savings percentage',
    analysis_data JSON NULL COMMENT 'Full analysis results including all alternatives',
    analyzed_by INT UNSIGNED NULL COMMENT 'User who requested the analysis',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meter_id) REFERENCES meters(id) ON DELETE CASCADE,
    FOREIGN KEY (current_tariff_id) REFERENCES tariffs(id) ON DELETE SET NULL,
    FOREIGN KEY (recommended_tariff_id) REFERENCES tariffs(id) ON DELETE SET NULL,
    FOREIGN KEY (analyzed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_meter (meter_id),
    INDEX idx_current_tariff (current_tariff_id),
    INDEX idx_recommended_tariff (recommended_tariff_id),
    INDEX idx_created (created_at),
    INDEX idx_savings (potential_savings)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
