-- eclectyc-energy/database/migrations/009_create_user_permissions.sql
-- Create user permissions system for granular access control
-- Last updated: 2025-11-09

-- Create permissions table to define all available permissions
CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE COMMENT 'Permission identifier (e.g., import.upload)',
    display_name VARCHAR(255) NOT NULL COMMENT 'Human-readable permission name',
    description TEXT NULL COMMENT 'Description of what this permission allows',
    category VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT 'Permission category for grouping',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Whether this permission is currently active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create user_permissions junction table for many-to-many relationship
CREATE TABLE IF NOT EXISTS user_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    granted_by INT UNSIGNED NULL COMMENT 'User who granted this permission',
    UNIQUE KEY unique_user_permission (user_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_permission (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default permissions for all site features
INSERT INTO permissions (name, display_name, description, category) VALUES
-- Import permissions
('import.view', 'View Imports', 'Access to view import page and import history', 'imports'),
('import.upload', 'Upload Import Files', 'Ability to upload and process CSV import files', 'imports'),
('import.manage_jobs', 'Manage Import Jobs', 'Ability to view, cancel, and delete import jobs', 'imports'),
('import.retry', 'Retry Failed Imports', 'Ability to retry failed import batches', 'imports'),

-- Export permissions
('export.view', 'View Exports', 'Access to view export functionality', 'exports'),
('export.create', 'Create Exports', 'Ability to create and download data exports', 'exports'),

-- User management permissions
('users.view', 'View Users', 'Access to view user list', 'users'),
('users.create', 'Create Users', 'Ability to create new user accounts', 'users'),
('users.edit', 'Edit Users', 'Ability to edit existing user accounts', 'users'),
('users.delete', 'Delete Users', 'Ability to delete user accounts', 'users'),
('users.manage_permissions', 'Manage User Permissions', 'Ability to grant/revoke user permissions', 'users'),

-- Meter management permissions
('meters.view', 'View Meters', 'Access to view meter list and details', 'meters'),
('meters.create', 'Create Meters', 'Ability to create new meters', 'meters'),
('meters.edit', 'Edit Meters', 'Ability to edit meter information', 'meters'),
('meters.delete', 'Delete Meters', 'Ability to delete meters', 'meters'),
('meters.view_carbon', 'View Carbon Intensity', 'Access to view meter carbon intensity data', 'meters'),

-- Site management permissions
('sites.view', 'View Sites', 'Access to view site list and details', 'sites'),
('sites.create', 'Create Sites', 'Ability to create new sites', 'sites'),
('sites.edit', 'Edit Sites', 'Ability to edit site information', 'sites'),
('sites.delete', 'Delete Sites', 'Ability to delete sites', 'sites'),

-- Tariff management permissions
('tariffs.view', 'View Tariffs', 'Access to view tariff list and details', 'tariffs'),
('tariffs.create', 'Create Tariffs', 'Ability to create new tariffs', 'tariffs'),
('tariffs.edit', 'Edit Tariffs', 'Ability to edit tariff information', 'tariffs'),
('tariffs.delete', 'Delete Tariffs', 'Ability to delete tariffs', 'tariffs'),

-- Tariff switching permissions
('tariff_switching.view', 'View Tariff Analysis', 'Access to view tariff switching analysis', 'tariff_switching'),
('tariff_switching.analyze', 'Perform Tariff Analysis', 'Ability to run tariff switching analysis', 'tariff_switching'),
('tariff_switching.view_history', 'View Analysis History', 'Access to view historical tariff analyses', 'tariff_switching'),

-- Reports permissions
('reports.view', 'View Reports', 'Access to view reports section', 'reports'),
('reports.consumption', 'View Consumption Reports', 'Access to consumption reports', 'reports'),
('reports.costs', 'View Cost Reports', 'Access to cost reports', 'reports'),

-- System settings permissions
('settings.view', 'View Settings', 'Access to view system settings', 'settings'),
('settings.edit', 'Edit Settings', 'Ability to modify system settings', 'settings'),

-- Tools permissions
('tools.view', 'View Tools', 'Access to view tools section', 'tools'),
('tools.system_health', 'View System Health', 'Access to system health monitoring', 'tools'),
('tools.sftp', 'Manage SFTP Configurations', 'Ability to manage SFTP configurations', 'tools'),
('tools.logs', 'View System Logs', 'Access to view and clear system logs', 'tools'),

-- Dashboard permissions
('dashboard.view', 'View Dashboard', 'Access to view the main dashboard', 'general');

-- Grant all permissions to existing admin users by default
INSERT INTO user_permissions (user_id, permission_id)
SELECT u.id, p.id
FROM users u
CROSS JOIN permissions p
WHERE u.role = 'admin';

-- Grant read-only permissions to viewer users
INSERT INTO user_permissions (user_id, permission_id)
SELECT u.id, p.id
FROM users u
CROSS JOIN permissions p
WHERE u.role = 'viewer'
AND p.name IN (
    'dashboard.view',
    'import.view',
    'export.view',
    'meters.view',
    'meters.view_carbon',
    'sites.view',
    'tariffs.view',
    'tariff_switching.view',
    'tariff_switching.view_history',
    'reports.view',
    'reports.consumption',
    'reports.costs'
);

-- Grant manager permissions (read + write but not user management or critical system functions)
INSERT INTO user_permissions (user_id, permission_id)
SELECT u.id, p.id
FROM users u
CROSS JOIN permissions p
WHERE u.role = 'manager'
AND p.name NOT IN (
    'users.delete',
    'users.manage_permissions',
    'settings.edit',
    'tools.logs'
);
