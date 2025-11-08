-- ================================================================
-- Eclectyc Energy Platform - Complete Database Schema
-- Database: k87747_eclectyc
-- Generated: 2025-11-08
-- Version: 1.0.0
-- ================================================================
--
-- This file contains the complete database schema including:
-- - All table definitions from migrations 001-007
-- - Seed data for initial setup
-- - UK energy supplier tariffs (Q4 2024)
--
-- Usage:
--   mysql -u username -p k87747_eclectyc < database/k87747_eclectyc.sql
--
-- Or via phpMyAdmin:
--   Import this file through the Import tab
--
-- ================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `k87747_eclectyc`
--

-- ================================================================
-- MIGRATION 001: Core Tables
-- ================================================================

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','manager','viewer') COLLATE utf8mb4_unicode_ci DEFAULT 'viewer',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suppliers table
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Companies table
CREATE TABLE IF NOT EXISTS `companies` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `registration_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vat_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `primary_contact_id` int(10) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `registration_number` (`registration_number`),
  KEY `primary_contact_id` (`primary_contact_id`),
  KEY `idx_registration` (`registration_number`),
  CONSTRAINT `companies_ibfk_1` FOREIGN KEY (`primary_contact_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Regions table
CREATE TABLE IF NOT EXISTS `regions` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'UK',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sites table
CREATE TABLE IF NOT EXISTS `sites` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` int(10) UNSIGNED DEFAULT NULL,
  `region_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postcode` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `site_type` enum('office','warehouse','retail','industrial','residential','other') COLLATE utf8mb4_unicode_ci DEFAULT 'office',
  `floor_area` decimal(10,2) DEFAULT NULL COMMENT 'Square meters',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `region_id` (`region_id`),
  KEY `idx_postcode` (`postcode`),
  CONSTRAINT `sites_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sites_ibfk_2` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Meters table
CREATE TABLE IF NOT EXISTS `meters` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id` int(10) UNSIGNED DEFAULT NULL,
  `supplier_id` int(10) UNSIGNED DEFAULT NULL,
  `mpan` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Meter Point Administration Number',
  `serial_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meter_type` enum('electricity','gas') COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_smart_meter` tinyint(1) DEFAULT 0,
  `is_half_hourly` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `install_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `batch_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Batch ID of import that created this meter',
  PRIMARY KEY (`id`),
  UNIQUE KEY `mpan` (`mpan`),
  KEY `site_id` (`site_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `idx_meter_type` (`meter_type`),
  KEY `idx_half_hourly` (`is_half_hourly`),
  KEY `idx_batch_id` (`batch_id`),
  CONSTRAINT `meters_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `meters_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Meter readings table
CREATE TABLE IF NOT EXISTS `meter_readings` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `meter_id` int(10) UNSIGNED NOT NULL,
  `reading_date` date NOT NULL,
  `reading_time` time NOT NULL,
  `reading_value` decimal(12,4) NOT NULL COMMENT 'kWh or m3',
  `reading_type` enum('actual','estimated') COLLATE utf8mb4_unicode_ci DEFAULT 'actual',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `batch_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Batch ID of import that created this reading',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_meter_datetime` (`meter_id`,`reading_date`,`reading_time`),
  KEY `idx_meter_date` (`meter_id`,`reading_date`),
  KEY `idx_date` (`reading_date`),
  KEY `idx_batch_id` (`batch_id`),
  CONSTRAINT `meter_readings_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tariffs table
CREATE TABLE IF NOT EXISTS `tariffs` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `supplier_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `energy_type` enum('electricity','gas') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tariff_type` enum('fixed','variable','time_of_use','dynamic') COLLATE utf8mb4_unicode_ci DEFAULT 'fixed',
  `unit_rate` decimal(10,4) DEFAULT NULL COMMENT 'Pence per kWh',
  `standing_charge` decimal(10,4) DEFAULT NULL COMMENT 'Pence per day',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `peak_rate` decimal(10,4) DEFAULT NULL,
  `off_peak_rate` decimal(10,4) DEFAULT NULL,
  `weekend_rate` decimal(10,4) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `supplier_id` (`supplier_id`),
  KEY `idx_code` (`code`),
  KEY `idx_dates` (`valid_from`,`valid_to`),
  CONSTRAINT `tariffs_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exports table
CREATE TABLE IF NOT EXISTS `exports` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `export_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `export_format` enum('csv','json','xml','excel') COLLATE utf8mb4_unicode_ci DEFAULT 'csv',
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `error_message` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `exports_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit logs table
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int(10) UNSIGNED DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `parent_batch_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrations tracking table
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  `executed_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- MIGRATION 003: Analytics Tables
-- ================================================================

-- Daily aggregations table
CREATE TABLE IF NOT EXISTS `daily_aggregations` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `meter_id` int(10) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `total_consumption` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `peak_consumption` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `off_peak_consumption` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `min_reading` decimal(12,4) DEFAULT NULL,
  `max_reading` decimal(12,4) DEFAULT NULL,
  `reading_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_meter_date` (`meter_id`,`date`),
  KEY `idx_date` (`date`),
  CONSTRAINT `daily_aggregations_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Weekly aggregations table
CREATE TABLE IF NOT EXISTS `weekly_aggregations` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `meter_id` int(10) UNSIGNED NOT NULL,
  `week_start` date NOT NULL,
  `week_end` date NOT NULL,
  `total_consumption` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `peak_consumption` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `off_peak_consumption` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `min_daily_consumption` decimal(12,4) DEFAULT NULL,
  `max_daily_consumption` decimal(12,4) DEFAULT NULL,
  `day_count` int(11) NOT NULL DEFAULT 0,
  `reading_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_meter_week` (`meter_id`,`week_start`),
  KEY `idx_week_start` (`week_start`),
  CONSTRAINT `weekly_aggregations_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monthly aggregations table
CREATE TABLE IF NOT EXISTS `monthly_aggregations` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `meter_id` int(10) UNSIGNED NOT NULL,
  `month_start` date NOT NULL,
  `month_end` date NOT NULL,
  `total_consumption` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `peak_consumption` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `off_peak_consumption` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `min_daily_consumption` decimal(12,4) DEFAULT NULL,
  `max_daily_consumption` decimal(12,4) DEFAULT NULL,
  `day_count` int(11) NOT NULL DEFAULT 0,
  `reading_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_meter_month` (`meter_id`,`month_start`),
  KEY `idx_month_start` (`month_start`),
  CONSTRAINT `monthly_aggregations_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Annual aggregations table
CREATE TABLE IF NOT EXISTS `annual_aggregations` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `meter_id` int(10) UNSIGNED NOT NULL,
  `year` year(4) NOT NULL,
  `total_consumption` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `peak_consumption` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `off_peak_consumption` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `min_daily_consumption` decimal(12,4) DEFAULT NULL,
  `max_daily_consumption` decimal(12,4) DEFAULT NULL,
  `day_count` int(11) NOT NULL DEFAULT 0,
  `reading_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_meter_year` (`meter_id`,`year`),
  KEY `idx_year` (`year`),
  CONSTRAINT `annual_aggregations_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- External carbon intensity table
CREATE TABLE IF NOT EXISTS `external_carbon_intensity` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `region` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `datetime` datetime NOT NULL,
  `intensity` decimal(10,2) NOT NULL COMMENT 'gCO2/kWh',
  `forecast` decimal(10,2) DEFAULT NULL COMMENT 'Forecasted intensity',
  `actual` decimal(10,2) DEFAULT NULL COMMENT 'Actual intensity',
  `source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_region_datetime` (`region`,`datetime`),
  KEY `idx_region` (`region`),
  KEY `idx_datetime` (`datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data quality issues table
CREATE TABLE IF NOT EXISTS `data_quality_issues` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `meter_id` int(10) UNSIGNED NOT NULL,
  `issue_date` date NOT NULL,
  `issue_type` enum('missing_data','anomaly','outlier','negative_value','zero_reading') COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` enum('low','medium','high') COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue_data` json DEFAULT NULL,
  `is_resolved` tinyint(1) DEFAULT 0,
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `meter_id` (`meter_id`),
  KEY `resolved_by` (`resolved_by`),
  KEY `idx_meter` (`meter_id`),
  KEY `idx_issue_date` (`issue_date`),
  KEY `idx_resolved` (`is_resolved`),
  CONSTRAINT `data_quality_issues_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `data_quality_issues_ibfk_2` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scheduler executions table
CREATE TABLE IF NOT EXISTS `scheduler_executions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `range_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `duration_seconds` int(11) DEFAULT NULL,
  `status` enum('running','completed','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'running',
  `meters_processed` int(11) DEFAULT 0,
  `readings_processed` int(11) DEFAULT 0,
  `errors` int(11) DEFAULT 0,
  `warnings` int(11) DEFAULT 0,
  `error_message` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_range_type` (`range_type`),
  KEY `idx_start_time` (`start_time`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scheduler alerts table
CREATE TABLE IF NOT EXISTS `scheduler_alerts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `alert_type` enum('failure','warning','summary') COLLATE utf8mb4_unicode_ci NOT NULL,
  `range_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_alert_type` (`alert_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- MIGRATION 004 & 005: Import Jobs
-- ================================================================

-- Import jobs table
CREATE TABLE IF NOT EXISTS `import_jobs` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id` varchar(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `import_type` enum('hh','daily') COLLATE utf8mb4_unicode_ci DEFAULT 'hh',
  `status` enum('queued','processing','completed','failed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'queued',
  `progress` int(11) DEFAULT 0,
  `total_rows` int(11) DEFAULT NULL,
  `processed_rows` int(11) DEFAULT 0,
  `successful_rows` int(11) DEFAULT 0,
  `failed_rows` int(11) DEFAULT 0,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `queued_at` timestamp NULL DEFAULT current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dry_run` tinyint(1) DEFAULT 0,
  `retry_count` int(11) DEFAULT 0,
  `max_retries` int(11) DEFAULT 3,
  `parent_job_id` int(10) UNSIGNED DEFAULT NULL,
  `default_site_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Default site to assign to auto-created meters',
  `default_tariff_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Default tariff to assign to meters',
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `batch_id` (`batch_id`),
  KEY `user_id` (`user_id`),
  KEY `parent_job_id` (`parent_job_id`),
  KEY `idx_status` (`status`),
  KEY `idx_queued_at` (`queued_at`),
  KEY `idx_default_site` (`default_site_id`),
  KEY `idx_default_tariff` (`default_tariff_id`),
  CONSTRAINT `import_jobs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `import_jobs_ibfk_2` FOREIGN KEY (`parent_job_id`) REFERENCES `import_jobs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_import_jobs_site` FOREIGN KEY (`default_site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_import_jobs_tariff` FOREIGN KEY (`default_tariff_id`) REFERENCES `tariffs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- MIGRATION 006: Tariff Switching
-- ================================================================

-- Tariff switching analyses table
CREATE TABLE IF NOT EXISTS `tariff_switching_analyses` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `meter_id` int(10) UNSIGNED NOT NULL,
  `analysis_date` date NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `current_tariff_id` int(10) UNSIGNED NOT NULL,
  `recommended_tariff_id` int(10) UNSIGNED DEFAULT NULL,
  `current_cost` decimal(10,2) DEFAULT NULL,
  `recommended_cost` decimal(10,2) DEFAULT NULL,
  `potential_savings` decimal(10,2) DEFAULT NULL,
  `total_consumption` decimal(12,4) DEFAULT NULL,
  `analysis_data` json DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `meter_id` (`meter_id`),
  KEY `current_tariff_id` (`current_tariff_id`),
  KEY `recommended_tariff_id` (`recommended_tariff_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_analysis_date` (`analysis_date`),
  CONSTRAINT `tariff_switching_analyses_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tariff_switching_analyses_ibfk_2` FOREIGN KEY (`current_tariff_id`) REFERENCES `tariffs` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `tariff_switching_analyses_ibfk_3` FOREIGN KEY (`recommended_tariff_id`) REFERENCES `tariffs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tariff_switching_analyses_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- SEED DATA
-- ================================================================

-- Default platform users (password for all: admin123)
INSERT INTO `users` (`email`, `password_hash`, `name`, `role`) VALUES
('admin@eclectyc.energy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Admin', 'admin'),
('manager@eclectyc.energy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Operations Manager', 'manager'),
('viewer@eclectyc.energy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Read Only Analyst', 'viewer');

-- Insert suppliers
INSERT INTO `suppliers` (`name`, `code`, `contact_email`) VALUES
('British Gas', 'BG', 'contact@britishgas.co.uk'),
('EDF Energy', 'EDF', 'contact@edfenergy.com'),
('E.ON', 'EON', 'contact@eon.com'),
('Scottish Power', 'SP', 'contact@scottishpower.com'),
('Octopus Energy', 'OCT', 'contact@octopusenergy.com'),
('OVO Energy', 'OVO', 'contact@ovoenergy.com');

-- Insert regions
INSERT INTO `regions` (`name`, `code`) VALUES
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
INSERT INTO `companies` (`name`, `registration_number`, `vat_number`, `address`) VALUES
('Eclectyc Energy Ltd', '12345678', 'GB123456789', '123 Energy Street, Bolton, England, BL1 2AB');

-- Insert sample sites
INSERT INTO `sites` (`company_id`, `region_id`, `name`, `address`, `postcode`, `site_type`, `floor_area`) VALUES
(1, 8, 'Main Office', '123 Energy Street, Bolton, England', 'BL1 2AB', 'office', 500.00),
(1, 8, 'Warehouse A', '456 Industrial Park, Manchester', 'M1 3BC', 'warehouse', 2000.00),
(1, 1, 'London Branch', '789 Business Centre, London', 'SW1A 1AA', 'office', 300.00);

-- ================================================================
-- MIGRATION 007: UK Energy Tariffs Q4 2024
-- ================================================================

-- UK tariffs based on Ofgem price cap October-December 2024

-- British Gas - Electricity Standard Variable Tariff
INSERT INTO `tariffs` (`supplier_id`, `name`, `code`, `energy_type`, `tariff_type`, `unit_rate`, `standing_charge`, `valid_from`, `valid_to`, `is_active`)
SELECT id, 'British Gas Standard Variable (Oct-Dec 2024)', 'BG-SVT-Q42024', 'electricity', 'variable', 24.50, 60.99, '2024-10-01', '2024-12-31', TRUE
FROM suppliers WHERE code = 'BG' LIMIT 1;

-- British Gas - Gas Standard Variable Tariff
INSERT INTO `tariffs` (`supplier_id`, `name`, `code`, `energy_type`, `tariff_type`, `unit_rate`, `standing_charge`, `valid_from`, `valid_to`, `is_active`)
SELECT id, 'British Gas Gas Standard (Oct-Dec 2024)', 'BG-GAS-Q42024', 'gas', 'variable', 6.24, 31.66, '2024-10-01', '2024-12-31', TRUE
FROM suppliers WHERE code = 'BG' LIMIT 1;

-- EDF Energy - Electricity Standard Variable Tariff
INSERT INTO `tariffs` (`supplier_id`, `name`, `code`, `energy_type`, `tariff_type`, `unit_rate`, `standing_charge`, `valid_from`, `valid_to`, `is_active`)
SELECT id, 'EDF Energy Standard Variable (Oct-Dec 2024)', 'EDF-SVT-Q42024', 'electricity', 'variable', 24.50, 61.00, '2024-10-01', '2024-12-31', TRUE
FROM suppliers WHERE code = 'EDF' LIMIT 1;

-- EDF Energy - Gas Standard Variable Tariff
INSERT INTO `tariffs` (`supplier_id`, `name`, `code`, `energy_type`, `tariff_type`, `unit_rate`, `standing_charge`, `valid_from`, `valid_to`, `is_active`)
SELECT id, 'EDF Energy Gas Standard (Oct-Dec 2024)', 'EDF-GAS-Q42024', 'gas', 'variable', 6.20, 32.00, '2024-10-01', '2024-12-31', TRUE
FROM suppliers WHERE code = 'EDF' LIMIT 1;

-- Octopus Energy - Flexible Electricity Tariff
INSERT INTO `tariffs` (`supplier_id`, `name`, `code`, `energy_type`, `tariff_type`, `unit_rate`, `standing_charge`, `valid_from`, `valid_to`, `is_active`)
SELECT id, 'Octopus Flexible (Oct-Dec 2024)', 'OCT-FLEX-Q42024', 'electricity', 'variable', 24.00, 50.00, '2024-10-01', '2024-12-31', TRUE
FROM suppliers WHERE code = 'OCT' LIMIT 1;

-- Octopus Energy - Gas Flexible Tariff
INSERT INTO `tariffs` (`supplier_id`, `name`, `code`, `energy_type`, `tariff_type`, `unit_rate`, `standing_charge`, `valid_from`, `valid_to`, `is_active`)
SELECT id, 'Octopus Gas Flexible (Oct-Dec 2024)', 'OCT-GAS-Q42024', 'gas', 'variable', 6.10, 31.00, '2024-10-01', '2024-12-31', TRUE
FROM suppliers WHERE code = 'OCT' LIMIT 1;

-- OVO Energy - Electricity Standard Variable Tariff
INSERT INTO `tariffs` (`supplier_id`, `name`, `code`, `energy_type`, `tariff_type`, `unit_rate`, `standing_charge`, `valid_from`, `valid_to`, `is_active`)
SELECT id, 'OVO Standard Variable (Oct-Dec 2024)', 'OVO-SVT-Q42024', 'electricity', 'variable', 24.50, 53.00, '2024-10-01', '2024-12-31', TRUE
FROM suppliers WHERE code = 'OVO' LIMIT 1;

-- OVO Energy - Gas Standard Variable Tariff
INSERT INTO `tariffs` (`supplier_id`, `name`, `code`, `energy_type`, `tariff_type`, `unit_rate`, `standing_charge`, `valid_from`, `valid_to`, `is_active`)
SELECT id, 'OVO Gas Standard (Oct-Dec 2024)', 'OVO-GAS-Q42024', 'gas', 'variable', 6.20, 31.00, '2024-10-01', '2024-12-31', TRUE
FROM suppliers WHERE code = 'OVO' LIMIT 1;

-- ================================================================
-- ADDITIONAL SEED DATA
-- ================================================================

-- Insert sample meters for demonstration
INSERT INTO `meters` (`site_id`, `supplier_id`, `mpan`, `serial_number`, `meter_type`, `is_smart_meter`, `is_half_hourly`) VALUES
(1, 1, '00-111-222-333-444', 'SM001', 'electricity', 1, 1),
(1, 1, '00-111-222-333-445', 'GM001', 'gas', 0, 0),
(2, 2, '00-222-333-444-555', 'SM002', 'electricity', 1, 1),
(3, 5, '00-333-444-555-666', 'SM003', 'electricity', 1, 1);

-- Insert sample meter readings (last 7 days)
INSERT INTO `meter_readings` (`meter_id`, `reading_date`, `reading_time`, `reading_value`, `reading_type`) VALUES
-- Main Office electricity readings (Meter 1)
(1, '2025-11-01', '00:00:00', 110.2, 'actual'),
(1, '2025-11-02', '00:00:00', 105.8, 'actual'),
(1, '2025-11-03', '00:00:00', 98.6, 'actual'),
(1, '2025-11-04', '00:00:00', 102.4, 'actual'),
(1, '2025-11-05', '00:00:00', 99.7, 'actual'),
(1, '2025-11-06', '00:00:00', 101.3, 'actual'),
(1, '2025-11-07', '00:00:00', 103.5, 'actual'),
-- Warehouse electricity readings (Meter 3)
(3, '2025-11-01', '00:00:00', 478.3, 'actual'),
(3, '2025-11-02', '00:00:00', 462.5, 'actual'),
(3, '2025-11-03', '00:00:00', 441.9, 'actual'),
(3, '2025-11-04', '00:00:00', 455.6, 'actual'),
(3, '2025-11-05', '00:00:00', 448.2, 'actual'),
(3, '2025-11-06', '00:00:00', 452.7, 'actual'),
(3, '2025-11-07', '00:00:00', 465.1, 'actual');

-- Insert daily aggregations
INSERT INTO `daily_aggregations` (`meter_id`, `date`, `total_consumption`, `peak_consumption`, `off_peak_consumption`, `min_reading`, `max_reading`, `reading_count`) VALUES
(1, '2025-11-01', 110.2, 0.0, 110.2, 110.2, 110.2, 1),
(1, '2025-11-02', 105.8, 0.0, 105.8, 105.8, 105.8, 1),
(1, '2025-11-03', 98.6, 0.0, 98.6, 98.6, 98.6, 1),
(1, '2025-11-04', 102.4, 0.0, 102.4, 102.4, 102.4, 1),
(1, '2025-11-05', 99.7, 0.0, 99.7, 99.7, 99.7, 1),
(1, '2025-11-06', 101.3, 0.0, 101.3, 101.3, 101.3, 1),
(1, '2025-11-07', 103.5, 0.0, 103.5, 103.5, 103.5, 1),
(3, '2025-11-01', 478.3, 0.0, 478.3, 478.3, 478.3, 1),
(3, '2025-11-02', 462.5, 0.0, 462.5, 462.5, 462.5, 1),
(3, '2025-11-03', 441.9, 0.0, 441.9, 441.9, 441.9, 1),
(3, '2025-11-04', 455.6, 0.0, 455.6, 455.6, 455.6, 1),
(3, '2025-11-05', 448.2, 0.0, 448.2, 448.2, 448.2, 1),
(3, '2025-11-06', 452.7, 0.0, 452.7, 452.7, 452.7, 1),
(3, '2025-11-07', 465.1, 0.0, 465.1, 465.1, 465.1, 1);

-- Insert weekly aggregations
INSERT INTO `weekly_aggregations` (`meter_id`, `week_start`, `week_end`, `total_consumption`, `peak_consumption`, `off_peak_consumption`, `min_daily_consumption`, `max_daily_consumption`, `day_count`, `reading_count`) VALUES
(1, '2025-11-03', '2025-11-09', 505.5, 0.0, 505.5, 98.6, 103.5, 5, 5),
(3, '2025-11-03', '2025-11-09', 2263.5, 0.0, 2263.5, 441.9, 465.1, 5, 5);

-- Insert monthly aggregations
INSERT INTO `monthly_aggregations` (`meter_id`, `month_start`, `month_end`, `total_consumption`, `peak_consumption`, `off_peak_consumption`, `min_daily_consumption`, `max_daily_consumption`, `day_count`, `reading_count`) VALUES
(1, '2025-11-01', '2025-11-30', 721.5, 0.0, 721.5, 98.6, 110.2, 7, 7),
(3, '2025-11-01', '2025-11-30', 3204.3, 0.0, 3204.3, 441.9, 478.3, 7, 7);

-- Insert sample carbon intensity data
INSERT INTO `external_carbon_intensity` (`region`, `datetime`, `intensity`, `forecast`, `actual`, `source`) VALUES
('GB', '2025-11-08 00:00:00', 245.00, 245.00, 243.50, 'National Grid ESO'),
('GB', '2025-11-08 06:00:00', 185.00, 185.00, 187.20, 'National Grid ESO'),
('GB', '2025-11-08 12:00:00', 210.00, 210.00, 208.30, 'National Grid ESO'),
('GB', '2025-11-08 18:00:00', 265.00, 265.00, 267.80, 'National Grid ESO');

-- Insert initial audit log entry
INSERT INTO `audit_logs` (`user_id`, `action`, `entity_type`, `new_values`) VALUES
(1, 'database_seeded', 'system', '{"message": "Initial database seed completed", "version": "1.0.0", "date": "2025-11-08"}');

-- Record migrations
INSERT INTO `migrations` (`migration`, `batch`) VALUES
('001_create_tables', 1),
('002_add_audit_logs_status', 1),
('002_add_import_batch_tracking', 1),
('003_add_analytics_tables', 1),
('004_create_import_jobs_table', 1),
('005_enhance_import_jobs', 1),
('006_create_tariff_switching_analyses', 1),
('007_add_uk_energy_tariffs_2024', 1);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
