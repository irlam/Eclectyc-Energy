-- eclectyc-energy/database/seeds/seed_data.sql
-- Initial seed data for development and testing
-- Last updated: 06/11/2025 20:50:00

-- Insert default platform users (password for all: admin123)
INSERT INTO users (email, password_hash, name, role) VALUES
('admin@eclectyc.energy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Admin', 'admin'),
('manager@eclectyc.energy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Operations Manager', 'manager'),
('viewer@eclectyc.energy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Read Only Analyst', 'viewer');

-- Insert suppliers
INSERT INTO suppliers (name, code, contact_email) VALUES
('British Gas', 'BG', 'contact@britishgas.co.uk'),
('EDF Energy', 'EDF', 'contact@edfenergy.com'),
('E.ON', 'EON', 'contact@eon.com'),
('Scottish Power', 'SP', 'contact@scottishpower.com'),
('Octopus Energy', 'OCT', 'contact@octopusenergy.com');

-- Insert regions
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
('Northern Ireland', 'NI');

-- Insert sample company
INSERT INTO companies (name, registration_number, vat_number, address) VALUES
('Eclectyc Energy Ltd', '12345678', 'GB123456789', '123 Energy Street, Bolton, England, BL1 2AB');

-- Insert sample sites
INSERT INTO sites (company_id, region_id, name, address, postcode, site_type, floor_area) VALUES
(1, 8, 'Main Office', '123 Energy Street, Bolton, England', 'BL1 2AB', 'office', 500.00),
(1, 8, 'Warehouse A', '456 Industrial Park, Manchester', 'M1 3BC', 'warehouse', 2000.00),
(1, 1, 'London Branch', '789 Business Centre, London', 'SW1A 1AA', 'office', 300.00);

-- Insert sample meters
INSERT INTO meters (site_id, supplier_id, mpan, serial_number, meter_type, is_smart_meter, is_half_hourly) VALUES
(1, 1, '00-111-222-333-444', 'SM001', 'electricity', TRUE, TRUE),
(1, 1, '00-111-222-333-445', 'GM001', 'gas', FALSE, FALSE),
(2, 2, '00-222-333-444-555', 'SM002', 'electricity', TRUE, TRUE),
(3, 5, '00-333-444-555-666', 'SM003', 'electricity', TRUE, TRUE);

-- Insert sample tariffs
INSERT INTO tariffs (supplier_id, name, code, energy_type, tariff_type, unit_rate, standing_charge, valid_from) VALUES
(1, 'Standard Variable', 'BG-SVT-01', 'electricity', 'variable', 28.50, 45.00, '2024-01-01'),
(1, 'Economy 7', 'BG-E7-01', 'electricity', 'time_of_use', NULL, 45.00, '2024-01-01'),
(2, 'Fixed 12 Months', 'EDF-FIX12-01', 'electricity', 'fixed', 25.00, 40.00, '2024-01-01'),
(5, 'Agile Octopus', 'OCT-AGILE-01', 'electricity', 'dynamic', NULL, 38.00, '2024-01-01');

-- Update Economy 7 tariff with peak/off-peak rates
UPDATE tariffs 
SET peak_rate = 35.00, off_peak_rate = 15.00 
WHERE code = 'BG-E7-01';

-- Insert sample meter readings (30 Oct 2025 - 06 Nov 2025)
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
(3, '2025-11-06', '00:00:00', 452.7, 'actual');

-- Seed daily aggregations aligned with the sample readings
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
	(3, '2025-11-06', 452.7, 0.0, 452.7, 452.7, 452.7, 1);

-- Seed weekly aggregations for sample data
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
	(3, '2025-11-03', '2025-11-09', 1798.4, 0.0, 1798.4, 441.9, 455.6, 4, 4);

-- Seed monthly aggregations for sample data
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
	(3, '2025-11-01', '2025-11-30', 2739.2, 0.0, 2739.2, 441.9, 478.3, 6, 6);

-- Seed annual aggregations for sample data
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
	(3, '2025-01-01', '2025-12-31', 3615.2, 0.0, 3615.2, 425.8, 478.3, 8, 8);

-- Insert system settings
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('system.timezone', 'Europe/London', 'string', 'System timezone'),
('import.batch_size', '1000', 'integer', 'Number of records per import batch'),
('export.default_format', 'csv', 'string', 'Default export format'),
('email.notifications_enabled', 'true', 'boolean', 'Enable email notifications'),
('data.retention_days', '365', 'integer', 'Days to retain detailed data');

-- Insert initial audit log
INSERT INTO audit_logs (user_id, action, entity_type, entity_id) VALUES
(1, 'seed_database', 'system', 1);