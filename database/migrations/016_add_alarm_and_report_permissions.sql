-- eclectyc-energy/database/migrations/016_add_alarm_and_report_permissions.sql
-- Add permissions for alarms and scheduled reports
-- Last updated: 10/11/2025

-- Insert permissions for alarms
INSERT INTO permissions (name, display_name, description, category) VALUES
('alarm.view', 'View Alarms', 'Access to view alarms and alarm history', 'alarms'),
('alarm.create', 'Create Alarms', 'Ability to create new alarms', 'alarms'),
('alarm.edit', 'Edit Alarms', 'Ability to edit existing alarms', 'alarms'),
('alarm.delete', 'Delete Alarms', 'Ability to delete alarms', 'alarms');

-- Insert permissions for scheduled reports
INSERT INTO permissions (name, display_name, description, category) VALUES
('report.view', 'View Scheduled Reports', 'Access to view scheduled reports and execution history', 'reports'),
('report.create', 'Create Scheduled Reports', 'Ability to create new scheduled reports', 'reports'),
('report.edit', 'Edit Scheduled Reports', 'Ability to edit existing scheduled reports', 'reports'),
('report.delete', 'Delete Scheduled Reports', 'Ability to delete scheduled reports', 'reports'),
('report.run', 'Run Reports Manually', 'Ability to manually trigger report generation', 'reports');
