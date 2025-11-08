-- =====================================================
-- ECLECTYC ENERGY - DATABASE SEED DATA
-- =====================================================
-- Copy and paste this entire file into your MySQL client
-- or run: mysql -u username -p database_name < SEED_DATABASE.sql
-- Last updated: 08/11/2025
-- =====================================================

-- WARNING: This will insert sample data into your database
-- Review the data before running in a production environment

-- =====================================================
-- USERS (Default password for all: admin123)
-- =====================================================
INSERT INTO users (email, password_hash, name, role) VALUES
('admin@eclectyc.energy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Admin', 'admin'),
('manager@eclectyc.energy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Operations Manager', 'manager'),
('viewer@eclectyc.energy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Read Only Analyst', 'viewer')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- =====================================================
-- SUPPLIERS
-- =====================================================
INSERT INTO suppliers (name, code, contact_email) VALUES
('British Gas', 'BG', 'contact@britishgas.co.uk'),
('EDF Energy', 'EDF', 'contact@edfenergy.com'),
('E.ON Next', 'EON', 'contact@eon-next.com'),
('Scottish Power', 'SP', 'contact@scottishpower.com'),
('Octopus Energy', 'OCT', 'contact@octopusenergy.com'),
('OVO Energy', 'OVO', 'contact@ovoenergy.com'),
('Utility Warehouse', 'UW', 'contact@utilitywarehouse.co.uk'),
('SSE Energy', 'SSE', 'contact@sse.co.uk'),
('Utilita Energy', 'UTL', 'contact@utilita.co.uk'),
('Shell Energy', 'SHELL', 'contact@shellenergy.co.uk')
ON DUPLICATE KEY UPDATE name = VALUES(name), contact_email = VALUES(contact_email);

-- =====================================================
-- REGIONS
-- =====================================================
INSERT INTO regions (name, code) VALUES
('London', 'LON'),
('South East', 'SE'),
('South West', 'SW'),
('East of England', 'EE'),
('West Midlands', 'WM'),
('East Midlands', 'EM'),
('Yorkshire', 'YH'),
('North West', 'NW'),
('North East', 'NE'),
('Scotland', 'SCO'),
('Wales', 'WAL'),
('Northern Ireland', 'NI')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- =====================================================
-- COMPANIES
-- =====================================================
INSERT INTO companies (name, registration_number, vat_number, address) VALUES
('Eclectyc Energy Ltd', '12345678', 'GB123456789', '123 Energy Street, Bolton, England, BL1 2AB')
ON DUPLICATE KEY UPDATE 
    registration_number = VALUES(registration_number),
    vat_number = VALUES(vat_number),
    address = VALUES(address);

-- =====================================================
-- SITES (Using company_id=1, adjust if needed)
-- =====================================================
INSERT INTO sites (company_id, region_id, name, address, postcode, site_type, floor_area) VALUES
(1, 8, 'Main Office', '123 Energy Street, Bolton, England', 'BL1 2AB', 'office', 500.00),
(1, 8, 'Warehouse A', '456 Industrial Park, Manchester', 'M1 3BC', 'warehouse', 2000.00),
(1, 1, 'London Branch', '789 Business Centre, London', 'SW1A 1AA', 'office', 300.00)
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    address = VALUES(address),
    postcode = VALUES(postcode),
    site_type = VALUES(site_type),
    floor_area = VALUES(floor_area);

-- =====================================================
-- METERS
-- =====================================================
INSERT INTO meters (site_id, supplier_id, mpan, serial_number, meter_type, is_smart_meter, is_half_hourly) VALUES
(1, 1, '00-111-222-333-444', 'SM001', 'electricity', TRUE, TRUE),
(1, 1, '00-111-222-333-445', 'GM001', 'gas', FALSE, FALSE),
(2, 2, '00-222-333-444-555', 'SM002', 'electricity', TRUE, TRUE),
(3, 5, '00-333-444-555-666', 'SM003', 'electricity', TRUE, TRUE)
ON DUPLICATE KEY UPDATE 
    serial_number = VALUES(serial_number),
    meter_type = VALUES(meter_type),
    is_smart_meter = VALUES(is_smart_meter),
    is_half_hourly = VALUES(is_half_hourly);

-- =====================================================
-- TARIFFS (Electricity & Gas) - 2025 UK Market Rates
-- =====================================================
INSERT INTO tariffs (supplier_id, name, code, energy_type, tariff_type, unit_rate, standing_charge, valid_from) VALUES
-- British Gas Tariffs
(1, 'Standard Variable Electricity', 'BG-SVT-ELEC-01', 'electricity', 'variable', 26.35, 53.68, '2024-10-01'),
(1, 'Fixed Tariff v81 12M', 'BG-FIX12-V81', 'electricity', 'fixed', 25.00, 50.00, '2025-01-01'),
(1, 'Electric Driver v16', 'BG-EV-V16', 'electricity', 'time_of_use', NULL, 52.00, '2025-01-01'),
(1, 'Economy 7 Electricity', 'BG-E7-01', 'electricity', 'time_of_use', NULL, 54.00, '2024-01-01'),
(1, 'Fixed 24 Months Electricity', 'BG-FIX24-01', 'electricity', 'fixed', 24.50, 48.00, '2025-01-01'),
(1, 'Standard Variable Gas', 'BG-SVT-GAS-01', 'gas', 'variable', 6.24, 31.00, '2024-10-01'),

-- EDF Energy Tariffs
(2, 'Standard Variable Electricity', 'EDF-STD-01', 'electricity', 'variable', 26.50, 49.00, '2024-10-01'),
(2, 'Fixed 12 Months v5', 'EDF-FIX12-V5', 'electricity', 'fixed', 25.00, 47.00, '2025-01-01'),
(2, 'Fixed 24 Months', 'EDF-FIX24-01', 'electricity', 'fixed', 24.80, 46.00, '2025-01-01'),
(2, 'GoElectric 35', 'EDF-GO35-01', 'electricity', 'time_of_use', NULL, 48.00, '2025-01-01'),
(2, 'Blue+ Price Promise', 'EDF-BLUE-01', 'electricity', 'variable', 26.00, 49.00, '2025-01-01'),
(2, 'Green Electricity', 'EDF-GREEN-01', 'electricity', 'variable', 26.80, 50.00, '2025-01-01'),

-- E.ON Next Tariffs
(3, 'Next Pledge Tracker', 'EON-PLEDGE-01', 'electricity', 'variable', 26.40, 56.00, '2024-10-01'),
(3, 'Next Drive v9', 'EON-DRIVE-V9', 'electricity', 'time_of_use', NULL, 55.00, '2025-01-01'),
(3, 'Fixed 1 Year v12', 'EON-FIX1-V12', 'electricity', 'fixed', 25.20, 54.00, '2025-01-01'),
(3, 'Fixed 2 Year', 'EON-FIX2-01', 'electricity', 'fixed', 24.90, 52.00, '2025-01-01'),

-- Scottish Power Tariffs
(4, 'Standard Price Cap', 'SP-STD-CAP-01', 'electricity', 'variable', 26.60, 60.00, '2024-10-01'),
(4, 'Fixed 1 Year', 'SP-FIX1-01', 'electricity', 'fixed', 25.50, 58.00, '2025-01-01'),
(4, 'Fixed 2 Year', 'SP-FIX2-01', 'electricity', 'fixed', 25.00, 56.00, '2025-01-01'),
(4, 'Fixed 3 Year', 'SP-FIX3-01', 'electricity', 'fixed', 24.80, 55.00, '2025-01-01'),
(4, 'EV Optimise', 'SP-EV-OPT-01', 'electricity', 'time_of_use', NULL, 57.00, '2025-01-01'),

-- Octopus Energy Tariffs
(5, 'Flexible Octopus', 'OCT-FLEX-01', 'electricity', 'variable', 24.50, 45.00, '2024-10-01'),
(5, 'Octopus 12M Fixed v6', 'OCT-FIX12-V6', 'electricity', 'fixed', 24.00, 44.00, '2025-01-01'),
(5, 'Intelligent Octopus Go', 'OCT-GO-01', 'electricity', 'time_of_use', NULL, 47.00, '2025-01-01'),
(5, 'Agile Octopus', 'OCT-AGILE-01', 'electricity', 'dynamic', NULL, 46.00, '2024-01-01'),
(5, 'Octopus Tracker', 'OCT-TRACKER-01', 'electricity', 'variable', 23.70, 45.50, '2025-01-01'),

-- OVO Energy Tariffs
(6, 'Standard Variable', 'OVO-STD-01', 'electricity', 'variable', 26.40, 58.00, '2024-10-01'),
(6, '1 Year Fixed 24', 'OVO-FIX1-24', 'electricity', 'fixed', 25.10, 56.00, '2025-01-01'),
(6, 'Charge Anytime', 'OVO-EV-ANYTIME', 'electricity', 'time_of_use', NULL, 57.00, '2025-01-01'),
(6, 'Zero Carbon', 'OVO-ZERO-01', 'electricity', 'variable', 26.80, 59.00, '2025-01-01'),

-- Utility Warehouse Tariffs
(7, 'Club Tariff', 'UW-CLUB-01', 'electricity', 'variable', 26.00, 47.00, '2024-10-01'),
(7, 'Fixed 12 Months', 'UW-FIX12-01', 'electricity', 'fixed', 25.00, 45.00, '2025-01-01'),

-- SSE Energy Tariffs
(8, 'Standard Variable', 'SSE-STD-01', 'electricity', 'variable', 26.70, 61.00, '2024-10-01'),
(8, 'Fixed 1 Year v8', 'SSE-FIX1-V8', 'electricity', 'fixed', 25.80, 60.00, '2025-01-01'),

-- Utilita Energy Tariffs (No Standing Charge Option)
(9, 'Smart PAYG No Standing Charge', 'UTL-PAYG-NSC', 'electricity', 'variable', 52.55, 0.00, '2025-03-01'),
(9, 'Standard PAYG', 'UTL-PAYG-STD', 'electricity', 'variable', 25.54, 53.68, '2024-10-01'),

-- Shell Energy Tariffs
(10, 'Fixed 12 Months', 'SHELL-FIX12-01', 'electricity', 'fixed', 25.30, 51.00, '2025-01-01'),
(10, 'Variable', 'SHELL-VAR-01', 'electricity', 'variable', 26.20, 52.00, '2024-10-01')
ON DUPLICATE KEY UPDATE 
    name = VALUES(name),
    unit_rate = VALUES(unit_rate),
    standing_charge = VALUES(standing_charge);

-- Update Time of Use tariffs with peak/off-peak rates
UPDATE tariffs 
SET peak_rate = 35.00, off_peak_rate = 15.00 
WHERE code = 'BG-E7-01';

UPDATE tariffs 
SET peak_rate = 28.00, off_peak_rate = 12.50 
WHERE code = 'BG-EV-V16';

UPDATE tariffs 
SET peak_rate = 32.00, off_peak_rate = 13.50 
WHERE code = 'EDF-GO35-01';

UPDATE tariffs 
SET peak_rate = 28.50, off_peak_rate = 6.70 
WHERE code = 'EON-DRIVE-V9';

UPDATE tariffs 
SET peak_rate = 30.00, off_peak_rate = 7.50 
WHERE code = 'OCT-GO-01';

UPDATE tariffs 
SET peak_rate = 29.00, off_peak_rate = 8.00 
WHERE code = 'SP-EV-OPT-01';

UPDATE tariffs 
SET peak_rate = 30.50, off_peak_rate = 9.00 
WHERE code = 'OVO-EV-ANYTIME';

-- =====================================================
-- METER READINGS (Sample data: 30 Oct 2025 - 06 Nov 2025)
-- =====================================================
INSERT INTO meter_readings (meter_id, reading_date, reading_time, reading_value, reading_type) VALUES
-- Main Office electricity readings
(1, '2025-10-30', '00:00:00', 100.5, 'actual'),
(1, '2025-10-31', '00:00:00', 95.3, 'actual'),
(1, '2025-11-01', '00:00:00', 110.2, 'actual'),
(1, '2025-11-02', '00:00:00', 105.8, 'actual'),
(1, '2025-11-03', '00:00:00', 98.6, 'actual'),
(1, '2025-11-04', '00:00:00', 102.4, 'actual'),
(1, '2025-11-05', '00:00:00', 99.7, 'actual'),
(1, '2025-11-06', '00:00:00', 101.3, 'actual'),

-- Warehouse electricity readings
(3, '2025-10-30', '00:00:00', 450.2, 'actual'),
(3, '2025-10-31', '00:00:00', 425.8, 'actual'),
(3, '2025-11-01', '00:00:00', 478.3, 'actual'),
(3, '2025-11-02', '00:00:00', 462.5, 'actual'),
(3, '2025-11-03', '00:00:00', 441.9, 'actual'),
(3, '2025-11-04', '00:00:00', 455.6, 'actual'),
(3, '2025-11-05', '00:00:00', 448.2, 'actual'),
(3, '2025-11-06', '00:00:00', 452.7, 'actual')
ON DUPLICATE KEY UPDATE 
    reading_value = VALUES(reading_value),
    reading_type = VALUES(reading_type);

-- =====================================================
-- DAILY AGGREGATIONS
-- =====================================================
INSERT INTO daily_aggregations (
    meter_id,
    date,
    total_consumption,
    peak_consumption,
    off_peak_consumption,
    min_reading,
    max_reading,
    reading_count
) VALUES
    (1, '2025-10-30', 100.5, 0.0, 100.5, 100.5, 100.5, 1),
    (1, '2025-10-31', 95.3, 0.0, 95.3, 95.3, 95.3, 1),
    (1, '2025-11-01', 110.2, 0.0, 110.2, 110.2, 110.2, 1),
    (1, '2025-11-02', 105.8, 0.0, 105.8, 105.8, 105.8, 1),
    (1, '2025-11-03', 98.6, 0.0, 98.6, 98.6, 98.6, 1),
    (1, '2025-11-04', 102.4, 0.0, 102.4, 102.4, 102.4, 1),
    (1, '2025-11-05', 99.7, 0.0, 99.7, 99.7, 99.7, 1),
    (1, '2025-11-06', 101.3, 0.0, 101.3, 101.3, 101.3, 1),
    (3, '2025-10-30', 450.2, 0.0, 450.2, 450.2, 450.2, 1),
    (3, '2025-10-31', 425.8, 0.0, 425.8, 425.8, 425.8, 1),
    (3, '2025-11-01', 478.3, 0.0, 478.3, 478.3, 478.3, 1),
    (3, '2025-11-02', 462.5, 0.0, 462.5, 462.5, 462.5, 1),
    (3, '2025-11-03', 441.9, 0.0, 441.9, 441.9, 441.9, 1),
    (3, '2025-11-04', 455.6, 0.0, 455.6, 455.6, 455.6, 1),
    (3, '2025-11-05', 448.2, 0.0, 448.2, 448.2, 448.2, 1),
    (3, '2025-11-06', 452.7, 0.0, 452.7, 452.7, 452.7, 1)
ON DUPLICATE KEY UPDATE 
    total_consumption = VALUES(total_consumption),
    reading_count = VALUES(reading_count);

-- =====================================================
-- WEEKLY AGGREGATIONS
-- =====================================================
INSERT INTO weekly_aggregations (
    meter_id,
    week_start,
    week_end,
    total_consumption,
    peak_consumption,
    off_peak_consumption,
    min_daily_consumption,
    max_daily_consumption,
    day_count,
    reading_count
) VALUES
    (1, '2025-10-27', '2025-11-02', 411.8, 0.0, 411.8, 95.3, 110.2, 4, 4),
    (1, '2025-11-03', '2025-11-09', 402.0, 0.0, 402.0, 98.6, 102.4, 4, 4),
    (3, '2025-10-27', '2025-11-02', 1816.8, 0.0, 1816.8, 425.8, 478.3, 4, 4),
    (3, '2025-11-03', '2025-11-09', 1798.4, 0.0, 1798.4, 441.9, 455.6, 4, 4)
ON DUPLICATE KEY UPDATE 
    total_consumption = VALUES(total_consumption),
    reading_count = VALUES(reading_count);

-- =====================================================
-- MONTHLY AGGREGATIONS
-- =====================================================
INSERT INTO monthly_aggregations (
    meter_id,
    month_start,
    month_end,
    total_consumption,
    peak_consumption,
    off_peak_consumption,
    min_daily_consumption,
    max_daily_consumption,
    day_count,
    reading_count
) VALUES
    (1, '2025-10-01', '2025-10-31', 195.8, 0.0, 195.8, 95.3, 100.5, 2, 2),
    (1, '2025-11-01', '2025-11-30', 618.0, 0.0, 618.0, 98.6, 110.2, 6, 6),
    (3, '2025-10-01', '2025-10-31', 876.0, 0.0, 876.0, 425.8, 450.2, 2, 2),
    (3, '2025-11-01', '2025-11-30', 2739.2, 0.0, 2739.2, 441.9, 478.3, 6, 6)
ON DUPLICATE KEY UPDATE 
    total_consumption = VALUES(total_consumption),
    reading_count = VALUES(reading_count);

-- =====================================================
-- ANNUAL AGGREGATIONS
-- =====================================================
INSERT INTO annual_aggregations (
    meter_id,
    year_start,
    year_end,
    total_consumption,
    peak_consumption,
    off_peak_consumption,
    min_daily_consumption,
    max_daily_consumption,
    day_count,
    reading_count
) VALUES
    (1, '2025-01-01', '2025-12-31', 813.8, 0.0, 813.8, 95.3, 110.2, 8, 8),
    (3, '2025-01-01', '2025-12-31', 3615.2, 0.0, 3615.2, 425.8, 478.3, 8, 8)
ON DUPLICATE KEY UPDATE 
    total_consumption = VALUES(total_consumption),
    reading_count = VALUES(reading_count);

-- =====================================================
-- SYSTEM SETTINGS
-- =====================================================
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('system.timezone', 'Europe/London', 'string', 'System timezone'),
('import.batch_size', '1000', 'integer', 'Number of records per import batch'),
('export.default_format', 'csv', 'string', 'Default export format'),
('email.notifications_enabled', 'true', 'boolean', 'Enable email notifications'),
('data.retention_days', '365', 'integer', 'Days to retain detailed data')
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    description = VALUES(description);

-- =====================================================
-- AUDIT LOG
-- =====================================================
INSERT INTO audit_logs (user_id, action, entity_type, entity_id, created_at) VALUES
(1, 'seed_database', 'system', 1, NOW());

-- =====================================================
-- SEED COMPLETE
-- =====================================================
-- Sample data has been inserted successfully!
-- 
-- Summary:
-- - 3 Users (admin, manager, viewer) - Password: admin123
-- - 10 Energy suppliers (British Gas, EDF, E.ON Next, Scottish Power, Octopus, OVO, Utility Warehouse, SSE, Utilita, Shell)
-- - 12 UK regions  
-- - 1 Company (Eclectyc Energy Ltd)
-- - 3 Sites
-- - 4 Meters (3 electricity, 1 gas)
-- - 41 Electricity Tariffs (2025 UK Market Rates)
--   * Variable rate tariffs (price cap aligned)
--   * Fixed rate tariffs (1-3 years)
--   * Time of Use (Economy 7, EV tariffs, GoElectric)
--   * Dynamic pricing (Agile Octopus, Tracker)
--   * EV-focused tariffs (off-peak as low as 6.7p/kWh)
--   * No standing charge option (Utilita)
-- - 1 Gas tariff
-- - Sample meter readings (30 Oct - 06 Nov 2025)
-- - Aggregation tables populated (daily/weekly/monthly/annual)
-- - System settings configured
--
-- Tariff rates based on 2025 UK market research:
--   * Unit rates: 23.7p - 52.55p per kWh
--   * Standing charges: 0p - 61p per day
--   * EV off-peak rates: 6.7p - 15p per kWh
--
-- IMPORTANT: Change the default admin password immediately!
-- =====================================================
