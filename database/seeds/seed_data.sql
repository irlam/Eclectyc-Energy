-- eclectyc-energy/database/seeds/seed_data.sql
-- Initial seed data for development and testing
-- Last updated: 06/11/2024 14:45:00

USE energy_platform;

-- Insert default admin user (password: admin123)
INSERT INTO users (email, password_hash, name, role) VALUES
('admin@eclectyc.energy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Admin', 'admin');

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

-- Insert some sample meter readings (last 7 days)
INSERT INTO meter_readings (meter_id, reading_date, reading_time, reading_value, reading_type) VALUES
-- Main Office electricity readings
(1, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '00:00:00', 100.5, 'actual'),
(1, DATE_SUB(CURDATE(), INTERVAL 6 DAY), '00:00:00', 95.3, 'actual'),
(1, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '00:00:00', 110.2, 'actual'),
(1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '00:00:00', 105.8, 'actual'),
(1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '00:00:00', 98.6, 'actual'),
(1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '00:00:00', 102.4, 'actual'),
(1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '00:00:00', 99.7, 'actual'),
(1, CURDATE(), '00:00:00', 101.3, 'actual'),

-- Warehouse electricity readings
(3, DATE_SUB(CURDATE(), INTERVAL 7 DAY), '00:00:00', 450.2, 'actual'),
(3, DATE_SUB(CURDATE(), INTERVAL 6 DAY), '00:00:00', 425.8, 'actual'),
(3, DATE_SUB(CURDATE(), INTERVAL 5 DAY), '00:00:00', 478.3, 'actual'),
(3, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '00:00:00', 462.5, 'actual'),
(3, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '00:00:00', 441.9, 'actual'),
(3, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '00:00:00', 455.6, 'actual'),
(3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '00:00:00', 448.2, 'actual'),
(3, CURDATE(), '00:00:00', 452.7, 'actual');

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