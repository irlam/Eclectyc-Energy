-- eclectyc-energy/database/migrations/007_add_uk_energy_tariffs_2024.sql
-- Add UK energy supplier tariffs based on Ofgem price cap October-December 2024
-- Last updated: 08/11/2025

-- Add OVO Energy supplier if not exists
INSERT INTO suppliers (name, code, contact_email, is_active)
SELECT 'OVO Energy', 'OVO', 'contact@ovoenergy.com', TRUE
WHERE NOT EXISTS (SELECT 1 FROM suppliers WHERE code = 'OVO');

-- Update existing suppliers to ensure they're active
UPDATE suppliers SET is_active = TRUE WHERE code IN ('BG', 'EDF', 'OCT', 'OVO');

-- Insert UK tariffs for October-December 2024 based on Ofgem price cap
-- All rates are in pence (p) as per table schema

-- British Gas - Standard Variable Tariff (Price Cap)
INSERT INTO tariffs (supplier_id, name, code, energy_type, tariff_type, unit_rate, standing_charge, valid_from, valid_to, is_active)
SELECT 
    (SELECT id FROM suppliers WHERE code = 'BG'),
    'British Gas Standard Variable (Oct-Dec 2024)',
    'BG-SVT-Q42024',
    'electricity',
    'variable',
    24.50,  -- pence per kWh
    60.99,  -- pence per day
    '2024-10-01',
    '2024-12-31',
    TRUE
WHERE NOT EXISTS (SELECT 1 FROM tariffs WHERE code = 'BG-SVT-Q42024');

-- British Gas - Gas Standard Variable Tariff
INSERT INTO tariffs (supplier_id, name, code, energy_type, tariff_type, unit_rate, standing_charge, valid_from, valid_to, is_active)
SELECT 
    (SELECT id FROM suppliers WHERE code = 'BG'),
    'British Gas Gas Standard (Oct-Dec 2024)',
    'BG-GAS-Q42024',
    'gas',
    'variable',
    6.24,   -- pence per kWh
    31.66,  -- pence per day
    '2024-10-01',
    '2024-12-31',
    TRUE
WHERE NOT EXISTS (SELECT 1 FROM tariffs WHERE code = 'BG-GAS-Q42024');

-- EDF Energy - Standard Variable Tariff (Price Cap)
INSERT INTO tariffs (supplier_id, name, code, energy_type, tariff_type, unit_rate, standing_charge, valid_from, valid_to, is_active)
SELECT 
    (SELECT id FROM suppliers WHERE code = 'EDF'),
    'EDF Energy Standard Variable (Oct-Dec 2024)',
    'EDF-SVT-Q42024',
    'electricity',
    'variable',
    24.50,  -- pence per kWh
    61.00,  -- pence per day
    '2024-10-01',
    '2024-12-31',
    TRUE
WHERE NOT EXISTS (SELECT 1 FROM tariffs WHERE code = 'EDF-SVT-Q42024');

-- EDF Energy - Gas Standard Variable Tariff
INSERT INTO tariffs (supplier_id, name, code, energy_type, tariff_type, unit_rate, standing_charge, valid_from, valid_to, is_active)
SELECT 
    (SELECT id FROM suppliers WHERE code = 'EDF'),
    'EDF Energy Gas Standard (Oct-Dec 2024)',
    'EDF-GAS-Q42024',
    'gas',
    'variable',
    6.20,   -- pence per kWh
    32.00,  -- pence per day
    '2024-10-01',
    '2024-12-31',
    TRUE
WHERE NOT EXISTS (SELECT 1 FROM tariffs WHERE code = 'EDF-GAS-Q42024');

-- Octopus Energy - Flexible Tariff (competitive rates)
INSERT INTO tariffs (supplier_id, name, code, energy_type, tariff_type, unit_rate, standing_charge, valid_from, valid_to, is_active)
SELECT 
    (SELECT id FROM suppliers WHERE code = 'OCT'),
    'Octopus Flexible (Oct-Dec 2024)',
    'OCT-FLEX-Q42024',
    'electricity',
    'variable',
    24.00,  -- pence per kWh (typically slightly below cap)
    50.00,  -- pence per day (lower standing charge)
    '2024-10-01',
    '2024-12-31',
    TRUE
WHERE NOT EXISTS (SELECT 1 FROM tariffs WHERE code = 'OCT-FLEX-Q42024');

-- Octopus Energy - Gas Flexible Tariff
INSERT INTO tariffs (supplier_id, name, code, energy_type, tariff_type, unit_rate, standing_charge, valid_from, valid_to, is_active)
SELECT 
    (SELECT id FROM suppliers WHERE code = 'OCT'),
    'Octopus Gas Flexible (Oct-Dec 2024)',
    'OCT-GAS-Q42024',
    'gas',
    'variable',
    6.10,   -- pence per kWh
    31.00,  -- pence per day
    '2024-10-01',
    '2024-12-31',
    TRUE
WHERE NOT EXISTS (SELECT 1 FROM tariffs WHERE code = 'OCT-GAS-Q42024');

-- OVO Energy - Standard Variable Tariff (Price Cap)
INSERT INTO tariffs (supplier_id, name, code, energy_type, tariff_type, unit_rate, standing_charge, valid_from, valid_to, is_active)
SELECT 
    (SELECT id FROM suppliers WHERE code = 'OVO'),
    'OVO Standard Variable (Oct-Dec 2024)',
    'OVO-SVT-Q42024',
    'electricity',
    'variable',
    24.50,  -- pence per kWh
    53.00,  -- pence per day (competitive standing charge)
    '2024-10-01',
    '2024-12-31',
    TRUE
WHERE NOT EXISTS (SELECT 1 FROM tariffs WHERE code = 'OVO-SVT-Q42024');

-- OVO Energy - Gas Standard Variable Tariff
INSERT INTO tariffs (supplier_id, name, code, energy_type, tariff_type, unit_rate, standing_charge, valid_from, valid_to, is_active)
SELECT 
    (SELECT id FROM suppliers WHERE code = 'OVO'),
    'OVO Gas Standard (Oct-Dec 2024)',
    'OVO-GAS-Q42024',
    'gas',
    'variable',
    6.20,   -- pence per kWh
    31.00,  -- pence per day
    '2024-10-01',
    '2024-12-31',
    TRUE
WHERE NOT EXISTS (SELECT 1 FROM tariffs WHERE code = 'OVO-GAS-Q42024');

-- Add audit log entry
INSERT INTO audit_logs (user_id, action, entity_type, new_values, ip_address)
VALUES (
    NULL,
    'migration_007',
    'tariffs',
    JSON_OBJECT(
        'description', 'Added UK energy supplier tariffs for Q4 2024 based on Ofgem price cap',
        'suppliers', JSON_ARRAY('British Gas', 'EDF Energy', 'Octopus Energy', 'OVO Energy'),
        'tariffs_added', 8,
        'valid_period', 'October-December 2024'
    ),
    '127.0.0.1'
);
