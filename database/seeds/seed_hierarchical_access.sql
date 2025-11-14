-- eclectyc-energy/database/seeds/seed_hierarchical_access.sql
-- Sample hierarchical access data for testing
-- Last updated: 2025-11-10

-- This seed file demonstrates the hierarchical access control system
-- Run after the main seed_data.sql

-- Insert sample companies
INSERT IGNORE INTO companies (id, name, registration_number, address) VALUES
(1, 'JD Wetherspoon plc', 'JDW-001', 'Wetherspoon House, Central Park, Reeds Crescent, Watford WD24 4QL'),
(2, 'Acme Properties Ltd', 'ACME-001', '123 Business Park, London, UK'),
(3, 'Green Energy Solutions', 'GES-001', '456 Renewable Street, Manchester, UK');

-- Insert sample regions
INSERT IGNORE INTO regions (id, name, code, description) VALUES
(1, 'Northwest', 'NW', 'Northwest England region'),
(2, 'Southeast', 'SE', 'Southeast England region'),
(3, 'Midlands', 'ML', 'Midlands region'),
(4, 'London', 'LON', 'Greater London area');

-- Insert sample sites for JD Wetherspoon
INSERT IGNORE INTO sites (id, company_id, region_id, name, address, postcode, site_type) VALUES
-- Northwest sites
(1, 1, 1, 'Bolton - Spinning Mule', 'The Spinning Mule, Nelson Square, Bolton BL1 1JT', 'BL1 1JT', 'retail'),
(2, 1, 1, 'Manchester - Moon Under Water', 'The Moon Under Water, 68-74 Deansgate, Manchester M3 2FN', 'M3 2FN', 'retail'),
(3, 1, 1, 'Liverpool - Richard John Blackler', 'The Richard John Blackler, 41-45 Charlotte Row, Liverpool L1 1HW', 'L1 1HW', 'retail'),

-- Southeast sites  
(4, 1, 2, 'Brighton - Bright Helm', 'The Bright Helm, 13 Pavilion Buildings, Brighton BN1 1EE', 'BN1 1EE', 'retail'),
(5, 1, 2, 'Canterbury - Thomas Becket', 'The Thomas Becket, 21-25 Best Lane, Canterbury CT1 2JB', 'CT1 2JB', 'retail'),

-- London sites
(6, 1, 4, 'London - Hamilton Hall', 'The Hamilton Hall, Liverpool Street Station, London EC2M 7PY', 'EC2M 7PY', 'retail'),
(7, 1, 4, 'London - Knights Templar', 'The Knights Templar, 95 Chancery Lane, London WC2A 1DT', 'WC2A 1DT', 'retail');

-- Insert sample sites for other companies
INSERT IGNORE INTO sites (id, company_id, region_id, name, address, postcode, site_type) VALUES
(8, 2, 1, 'Merv''s House', '789 Residential Ave, Bolton BL2 1AA', 'BL2 1AA', 'residential'),
(9, 2, 4, 'Irlam''s House', '321 London Road, London SW1A 1AA', 'SW1A 1AA', 'residential'),
(10, 3, 3, 'Solar Farm Alpha', 'Green Fields, Birmingham B1 1AA', 'B1 1AA', 'industrial');

-- Create test users with different access levels
-- Password for all: admin123

-- User 1: Site-level access (can only see Merv's House)
INSERT IGNORE INTO users (id, email, password_hash, name, role, is_active) VALUES
(10, 'user1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'User One', 'viewer', 1);

-- User 2: Site-level access (can only see Irlam's House)  
INSERT IGNORE INTO users (id, email, password_hash, name, role, is_active) VALUES
(45, 'user45@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'User Forty Five', 'viewer', 1);

-- Regional Manager: Region-level access (can see all Northwest sites)
INSERT IGNORE INTO users (id, email, password_hash, name, role, is_active) VALUES
(11, 'regional.manager@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Regional Manager NW', 'manager', 1);

-- Energy Manager: Company-level access (can see all JDW sites)
INSERT IGNORE INTO users (id, email, password_hash, name, role, is_active) VALUES
(12, 'energy.manager@jdw.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'JDW Energy Manager', 'manager', 1);

-- Pub Manager: Site-level access (can only see Bolton - Spinning Mule)
INSERT IGNORE INTO users (id, email, password_hash, name, role, is_active) VALUES
(13, 'bolton.manager@jdw.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bolton Pub Manager', 'viewer', 1);

-- Grant hierarchical access
-- User 1: Access to Merv's House only
INSERT IGNORE INTO user_site_access (user_id, site_id) VALUES (10, 8);

-- User 45: Access to Irlam's House only
INSERT IGNORE INTO user_site_access (user_id, site_id) VALUES (45, 9);

-- Regional Manager: Access to Northwest region (all sites in Northwest)
INSERT IGNORE INTO user_region_access (user_id, region_id) VALUES (11, 1);

-- Energy Manager: Access to JDW company (all JDW sites)
INSERT IGNORE INTO user_company_access (user_id, company_id) VALUES (12, 1);

-- Pub Manager: Access to Bolton - Spinning Mule only
INSERT IGNORE INTO user_site_access (user_id, site_id) VALUES (13, 1);

-- Portfolio Manager: Access to all Acme Properties sites
INSERT IGNORE INTO users (id, email, password_hash, name, role, is_active) VALUES
(14, 'manager1@acme.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Portfolio Manager', 'manager', 1);

INSERT IGNORE INTO user_company_access (user_id, company_id) VALUES (14, 2);

-- Multi-region manager: Access to Southeast and London regions
INSERT IGNORE INTO users (id, email, password_hash, name, role, is_active) VALUES
(15, 'south.manager@jdw.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Southern Area Manager', 'manager', 1);

INSERT IGNORE INTO user_region_access (user_id, region_id) VALUES 
(15, 2),
(15, 4);
