-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 10.35.233.124:3306
-- Generation Time: Nov 09, 2025 at 10:57 AM
-- Server version: 8.0.43
-- PHP Version: 8.4.8

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `k87747_eclectyc`
--

-- --------------------------------------------------------

--
-- Table structure for table `ai_insights`
--

CREATE TABLE `ai_insights` (
  `id` bigint UNSIGNED NOT NULL,
  `meter_id` int UNSIGNED NOT NULL,
  `insight_date` date NOT NULL,
  `insight_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `recommendations` json DEFAULT NULL,
  `confidence_score` decimal(5,2) DEFAULT NULL COMMENT 'Percentage 0-100',
  `priority` enum('low','medium','high') COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `is_dismissed` tinyint(1) DEFAULT '0',
  `dismissed_by` int UNSIGNED DEFAULT NULL,
  `dismissed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `annual_aggregations`
--

CREATE TABLE `annual_aggregations` (
  `id` bigint UNSIGNED NOT NULL,
  `meter_id` int UNSIGNED NOT NULL,
  `year` year NOT NULL,
  `total_consumption` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `peak_consumption` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `off_peak_consumption` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `min_daily_consumption` decimal(12,4) DEFAULT NULL,
  `max_daily_consumption` decimal(12,4) DEFAULT NULL,
  `day_count` int NOT NULL DEFAULT '0',
  `reading_count` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` int UNSIGNED DEFAULT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int UNSIGNED DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retry_count` int DEFAULT '0',
  `parent_batch_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`, `status`, `retry_count`, `parent_batch_id`) VALUES
(1, NULL, 'migration_007', 'tariffs', NULL, NULL, '{\"suppliers\": [\"British Gas\", \"EDF Energy\", \"Octopus Energy\", \"OVO Energy\"], \"description\": \"Added UK energy supplier tariffs for Q4 2024 based on Ofgem price cap\", \"valid_period\": \"October-December 2024\", \"tariffs_added\": 8}', '127.0.0.1', NULL, '2025-11-08 11:43:54', NULL, 0, NULL),
(2, NULL, 'migration_007', 'tariffs', NULL, NULL, '{\"suppliers\": [\"British Gas\", \"EDF Energy\", \"Octopus Energy\", \"OVO Energy\"], \"description\": \"Added UK energy supplier tariffs for Q4 2024 based on Ofgem price cap\", \"valid_period\": \"October-December 2024\", \"tariffs_added\": 8}', '127.0.0.1', NULL, '2025-11-08 11:46:58', NULL, 0, NULL),
(3, 1, 'import_csv', 'import_batch', NULL, NULL, '{\"errors\": [], \"format\": \"hh-interval\", \"batch_id\": \"95c4d851-d48f-4a5f-82ff-e4235a2f6087\", \"records_failed\": 0, \"records_imported\": 157996, \"records_processed\": 157996}', NULL, NULL, '2025-11-08 14:36:04', 'completed', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `registration_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vat_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `billing_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `primary_contact_id` int UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `name`, `registration_number`, `vat_number`, `address`, `billing_address`, `primary_contact_id`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Eclectyc Energy Ltd', '12345678', 'GB123456789', '123 Energy Street, Bolton, England, BL1 2AB', NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(2, 'Default Company', NULL, NULL, NULL, NULL, NULL, 1, '2025-11-08 13:28:23', '2025-11-08 13:28:23');

-- --------------------------------------------------------

--
-- Table structure for table `comparison_snapshots`
--

CREATE TABLE `comparison_snapshots` (
  `id` bigint UNSIGNED NOT NULL,
  `meter_id` int UNSIGNED NOT NULL,
  `snapshot_date` date NOT NULL,
  `snapshot_type` enum('daily','weekly','monthly','annual') COLLATE utf8mb4_unicode_ci NOT NULL,
  `current_data` json NOT NULL,
  `comparison_data` json NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_aggregations`
--

CREATE TABLE `daily_aggregations` (
  `id` bigint UNSIGNED NOT NULL,
  `meter_id` int UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `total_consumption` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `peak_consumption` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `off_peak_consumption` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `min_reading` decimal(12,4) DEFAULT NULL,
  `max_reading` decimal(12,4) DEFAULT NULL,
  `reading_count` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `daily_aggregations`
--

INSERT INTO `daily_aggregations` (`id`, `meter_id`, `date`, `total_consumption`, `peak_consumption`, `off_peak_consumption`, `min_reading`, `max_reading`, `reading_count`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-10-30', 100.5000, 0.0000, 100.5000, 100.5000, 100.5000, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(2, 1, '2025-10-31', 95.3000, 0.0000, 95.3000, 95.3000, 95.3000, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(3, 1, '2025-11-01', 110.2000, 0.0000, 110.2000, 110.2000, 110.2000, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(4, 1, '2025-11-02', 105.8000, 0.0000, 105.8000, 105.8000, 105.8000, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(5, 1, '2025-11-03', 98.6000, 0.0000, 98.6000, 98.6000, 98.6000, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(6, 1, '2025-11-04', 102.4000, 0.0000, 102.4000, 102.4000, 102.4000, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(7, 1, '2025-11-05', 99.7000, 0.0000, 99.7000, 99.7000, 99.7000, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(8, 1, '2025-11-06', 101.3000, 0.0000, 101.3000, 101.3000, 101.3000, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(9, 3, '2025-10-30', 450.2000, 0.0000, 450.2000, 450.2000, 450.2000, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(10, 3, '2025-10-31', 425.8000, 0.0000, 425.8000, 425.8000, 425.8000, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(11, 3, '2025-11-01', 478.3000, 0.0000, 478.3000, 478.3000, 478.3000, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(12, 3, '2025-11-02', 462.5000, 0.0000, 462.5000, 462.5000, 462.5000, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(13, 3, '2025-11-03', 441.9000, 0.0000, 441.9000, 441.9000, 441.9000, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(14, 3, '2025-11-04', 455.6000, 0.0000, 455.6000, 455.6000, 455.6000, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(15, 3, '2025-11-05', 448.2000, 0.0000, 448.2000, 448.2000, 448.2000, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(16, 3, '2025-11-06', 452.7000, 0.0000, 452.7000, 452.7000, 452.7000, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37');

-- --------------------------------------------------------

--
-- Table structure for table `data_quality_issues`
--

CREATE TABLE `data_quality_issues` (
  `id` bigint UNSIGNED NOT NULL,
  `meter_id` int UNSIGNED NOT NULL,
  `issue_date` date NOT NULL,
  `issue_type` enum('missing_data','anomaly','outlier','negative_value','zero_reading') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` enum('low','medium','high') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `issue_data` json DEFAULT NULL,
  `is_resolved` tinyint(1) DEFAULT '0',
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exports`
--

CREATE TABLE `exports` (
  `id` int UNSIGNED NOT NULL,
  `export_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `export_format` enum('csv','json','xml','excel') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'csv',
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `external_calorific_values`
--

CREATE TABLE `external_calorific_values` (
  `id` bigint UNSIGNED NOT NULL,
  `region` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `calorific_value` decimal(10,4) NOT NULL COMMENT 'Energy content',
  `unit` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'MJ/m3',
  `source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `external_carbon_intensity`
--

CREATE TABLE `external_carbon_intensity` (
  `id` bigint UNSIGNED NOT NULL,
  `region` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `datetime` datetime NOT NULL,
  `intensity` decimal(10,2) NOT NULL COMMENT 'gCO2/kWh',
  `forecast` decimal(10,2) DEFAULT NULL COMMENT 'Forecasted intensity',
  `actual` decimal(10,2) DEFAULT NULL COMMENT 'Actual intensity',
  `source` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `external_temperature_data`
--

CREATE TABLE `external_temperature_data` (
  `id` bigint UNSIGNED NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `avg_temperature` decimal(5,2) DEFAULT NULL COMMENT 'Celsius',
  `min_temperature` decimal(5,2) DEFAULT NULL COMMENT 'Celsius',
  `max_temperature` decimal(5,2) DEFAULT NULL COMMENT 'Celsius',
  `source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `import_jobs`
--

CREATE TABLE `import_jobs` (
  `id` int UNSIGNED NOT NULL,
  `batch_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `import_type` enum('hh','daily') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'hh',
  `status` enum('queued','processing','completed','failed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'queued',
  `priority` enum('low','normal','high','critical') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `progress` int DEFAULT '0',
  `total_rows` int DEFAULT NULL,
  `processed_rows` int DEFAULT '0',
  `successful_rows` int DEFAULT '0',
  `failed_rows` int DEFAULT '0',
  `user_id` int UNSIGNED DEFAULT NULL,
  `queued_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `dry_run` tinyint(1) DEFAULT '0',
  `retry_count` int DEFAULT '0',
  `max_retries` int DEFAULT '3',
  `parent_job_id` int UNSIGNED DEFAULT NULL,
  `default_site_id` int UNSIGNED DEFAULT NULL COMMENT 'Default site to assign to auto-created meters',
  `default_tariff_id` int UNSIGNED DEFAULT NULL COMMENT 'Default tariff to assign to meters',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tags` json DEFAULT NULL,
  `metadata` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `import_jobs`
--

INSERT INTO `import_jobs` (`id`, `batch_id`, `filename`, `file_path`, `import_type`, `status`, `priority`, `progress`, `total_rows`, `processed_rows`, `successful_rows`, `failed_rows`, `user_id`, `queued_at`, `started_at`, `completed_at`, `error_message`, `dry_run`, `retry_count`, `max_retries`, `parent_job_id`, `default_site_id`, `default_tariff_id`, `notes`, `last_error`, `tags`, `metadata`) VALUES
(1, '1bd6023e-b2e7-4850-8e47-fae041eec284', 'Test_HH_Data.csv', '/var/www/vhosts/hosting215226.ae97b.netcup.net/eclectyc.energy/httpdocs/app/storage/imports/1762609049_Test_HH_Data.csv', 'hh', 'cancelled', '', 0, NULL, 0, 0, 0, 1, '2025-11-08 13:37:29', NULL, '2025-11-09 10:34:18', 'Job cancelled by user', 0, 0, 3, NULL, NULL, NULL, '3', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `meters`
--

CREATE TABLE `meters` (
  `id` int UNSIGNED NOT NULL,
  `site_id` int UNSIGNED DEFAULT NULL,
  `supplier_id` int UNSIGNED DEFAULT NULL,
  `mpan` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Meter Point Administration Number',
  `serial_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meter_type` enum('electricity','gas') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_smart_meter` tinyint(1) DEFAULT '0',
  `is_half_hourly` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `install_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `batch_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Batch ID of import that created this meter'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `meters`
--

INSERT INTO `meters` (`id`, `site_id`, `supplier_id`, `mpan`, `serial_number`, `meter_type`, `is_smart_meter`, `is_half_hourly`, `is_active`, `install_date`, `created_at`, `updated_at`, `batch_id`) VALUES
(1, 1, 1, '00-111-222-333-444', 'SM001', 'electricity', 1, 1, 1, NULL, '2025-11-08 13:00:37', '2025-11-08 13:00:37', NULL),
(2, 1, 1, '00-111-222-333-445', 'GM001', 'gas', 0, 0, 1, NULL, '2025-11-08 13:00:37', '2025-11-08 13:00:37', NULL),
(3, 2, 2, '00-222-333-444-555', 'SM002', 'electricity', 1, 1, 1, NULL, '2025-11-08 13:00:37', '2025-11-08 13:00:37', NULL),
(4, 3, 5, '00-333-444-555-666', 'SM003', 'electricity', 1, 1, 1, NULL, '2025-11-08 13:00:37', '2025-11-08 13:00:37', NULL),
(5, 4, NULL, 'E06BG12862', NULL, 'electricity', 0, 1, 1, NULL, '2025-11-08 13:28:23', '2025-11-08 13:28:23', NULL),
(6, 4, NULL, 'E07BG07453', NULL, 'electricity', 0, 1, 1, NULL, '2025-11-08 13:28:27', '2025-11-08 13:28:27', NULL),
(7, 4, NULL, 'E09BG14002', NULL, 'electricity', 0, 1, 1, NULL, '2025-11-08 13:28:31', '2025-11-08 13:28:31', NULL),
(8, 4, NULL, 'E09BG23735', NULL, 'electricity', 0, 1, 1, NULL, '2025-11-08 13:28:34', '2025-11-08 13:28:34', NULL),
(9, 4, NULL, 'E14ML01961', NULL, 'electricity', 0, 1, 1, NULL, '2025-11-08 13:28:38', '2025-11-08 13:28:38', NULL),
(10, 4, NULL, 'E18ML18100', NULL, 'electricity', 0, 1, 1, NULL, '2025-11-08 13:28:42', '2025-11-08 13:28:42', NULL),
(11, 4, NULL, 'E19ML13847', NULL, 'electricity', 0, 1, 1, NULL, '2025-11-08 13:28:46', '2025-11-08 13:28:46', NULL),
(12, 4, NULL, 'E19ML14968', NULL, 'electricity', 0, 1, 1, NULL, '2025-11-08 13:28:50', '2025-11-08 13:28:50', NULL),
(13, 4, NULL, 'E20ML07104', NULL, 'electricity', 0, 1, 1, NULL, '2025-11-08 13:28:54', '2025-11-08 13:28:54', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `meter_readings`
--

CREATE TABLE `meter_readings` (
  `id` bigint UNSIGNED NOT NULL,
  `meter_id` int UNSIGNED NOT NULL,
  `reading_date` date NOT NULL,
  `reading_time` time NOT NULL,
  `period_number` tinyint DEFAULT NULL COMMENT 'For half-hourly data: 1-48',
  `reading_value` decimal(12,4) NOT NULL COMMENT 'kWh or m3',
  `reading_type` enum('actual','estimated') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'actual',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `batch_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Batch ID of import that created this reading',
  `import_batch_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Identifier for import batch'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int UNSIGNED NOT NULL,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  `executed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `monthly_aggregations`
--

CREATE TABLE `monthly_aggregations` (
  `id` bigint UNSIGNED NOT NULL,
  `meter_id` int UNSIGNED NOT NULL,
  `month_start` date NOT NULL,
  `month_end` date NOT NULL,
  `total_consumption` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `peak_consumption` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `off_peak_consumption` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `min_daily_consumption` decimal(12,4) DEFAULT NULL,
  `max_daily_consumption` decimal(12,4) DEFAULT NULL,
  `day_count` int NOT NULL DEFAULT '0',
  `reading_count` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `monthly_aggregations`
--

INSERT INTO `monthly_aggregations` (`id`, `meter_id`, `month_start`, `month_end`, `total_consumption`, `peak_consumption`, `off_peak_consumption`, `min_daily_consumption`, `max_daily_consumption`, `day_count`, `reading_count`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-10-01', '2025-10-31', 195.8000, 0.0000, 195.8000, 95.3000, 100.5000, 2, 2, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(2, 1, '2025-11-01', '2025-11-30', 618.0000, 0.0000, 618.0000, 98.6000, 110.2000, 6, 6, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(3, 3, '2025-10-01', '2025-10-31', 876.0000, 0.0000, 876.0000, 425.8000, 450.2000, 2, 2, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(4, 3, '2025-11-01', '2025-11-30', 2739.2000, 0.0000, 2739.2000, 441.9000, 478.3000, 6, 6, '2025-11-08 13:00:37', '2025-11-08 13:00:37');

-- --------------------------------------------------------

--
-- Table structure for table `regions`
--

CREATE TABLE `regions` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'UK',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `regions`
--

INSERT INTO `regions` (`id`, `name`, `code`, `country`, `created_at`, `updated_at`) VALUES
(1, 'London', 'LON', 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(2, 'South East', 'SE', 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(3, 'South West', 'SW', 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(4, 'East of England', 'EE', 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(5, 'West Midlands', 'WM', 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(6, 'East Midlands', 'EM', 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(7, 'Yorkshire', 'YH', 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(8, 'North West', 'NW', 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(9, 'North East', 'NE', 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(10, 'Scotland', 'SCO', 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(11, 'Wales', 'WAL', 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(12, 'Northern Ireland', 'NI', 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37');

-- --------------------------------------------------------

--
-- Table structure for table `scheduler_alerts`
--

CREATE TABLE `scheduler_alerts` (
  `id` bigint UNSIGNED NOT NULL,
  `alert_type` enum('failure','warning','summary') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `range_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scheduler_executions`
--

CREATE TABLE `scheduler_executions` (
  `id` bigint UNSIGNED NOT NULL,
  `range_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `duration_seconds` int DEFAULT NULL,
  `status` enum('running','completed','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'running',
  `meters_processed` int DEFAULT '0',
  `readings_processed` int DEFAULT '0',
  `errors` int DEFAULT '0',
  `warnings` int DEFAULT '0',
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `setting_type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'string',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sftp_configurations`
--

CREATE TABLE `sftp_configurations` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Friendly name for the SFTP connection',
  `host` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SFTP server hostname or IP',
  `port` int UNSIGNED DEFAULT '22' COMMENT 'SFTP port (default 22)',
  `username` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SFTP username',
  `password` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Encrypted SFTP password',
  `private_key_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path to SSH private key file',
  `remote_directory` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '/' COMMENT 'Remote directory to monitor',
  `file_pattern` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '*.csv' COMMENT 'File pattern to match (e.g., *.csv, data_*.csv)',
  `import_type` enum('hh','daily') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'hh' COMMENT 'Default import type for files',
  `auto_import` tinyint(1) DEFAULT '0' COMMENT 'Automatically import matching files',
  `delete_after_import` tinyint(1) DEFAULT '0' COMMENT 'Delete files from SFTP after successful import',
  `is_active` tinyint(1) DEFAULT '1' COMMENT 'Whether this configuration is active',
  `last_connection_at` timestamp NULL DEFAULT NULL COMMENT 'Last successful connection timestamp',
  `last_error` text COLLATE utf8mb4_unicode_ci COMMENT 'Last connection error message',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sites`
--

CREATE TABLE `sites` (
  `id` int UNSIGNED NOT NULL,
  `company_id` int UNSIGNED DEFAULT NULL,
  `region_id` int UNSIGNED DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `postcode` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `site_type` enum('office','warehouse','retail','industrial','residential','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'office',
  `floor_area` decimal(10,2) DEFAULT NULL COMMENT 'Square meters',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sites`
--

INSERT INTO `sites` (`id`, `company_id`, `region_id`, `name`, `address`, `postcode`, `site_type`, `floor_area`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 8, 'Main Office', '123 Energy Street, Bolton, England', 'BL1 2AB', 'office', 500.00, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(2, 1, 8, 'Warehouse A', '456 Industrial Park, Manchester', 'M1 3BC', 'warehouse', 2000.00, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(3, 1, 1, 'London Branch', '789 Business Centre, London', 'SW1A 1AA', 'office', 300.00, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(4, 2, NULL, 'Auto-imported Meters', 'Auto-generated during CSV import', 'TBD', 'office', NULL, 1, '2025-11-08 13:28:23', '2025-11-08 13:28:23');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `name`, `code`, `contact_email`, `contact_phone`, `address`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'OVO Energy', 'OVO', 'contact@ovoenergy.com', NULL, NULL, 1, '2025-11-08 11:43:54', '2025-11-08 11:43:54'),
(2, 'British Gas', 'BG', 'contact@britishgas.co.uk', NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(3, 'EDF Energy', 'EDF', 'contact@edfenergy.com', NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(4, 'E.ON Next', 'EON', 'contact@eon-next.com', NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(5, 'Scottish Power', 'SP', 'contact@scottishpower.com', NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(6, 'Octopus Energy', 'OCT', 'contact@octopusenergy.com', NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(7, 'Utility Warehouse', 'UW', 'contact@utilitywarehouse.co.uk', NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(8, 'SSE Energy', 'SSE', 'contact@sse.co.uk', NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(9, 'Utilita Energy', 'UTL', 'contact@utilita.co.uk', NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(10, 'Shell Energy', 'SHELL', 'contact@shellenergy.co.uk', NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int UNSIGNED NOT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Setting identifier',
  `setting_value` text COLLATE utf8mb4_unicode_ci COMMENT 'Setting value',
  `setting_type` enum('string','integer','boolean','json') COLLATE utf8mb4_unicode_ci DEFAULT 'string' COMMENT 'Value type',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Setting description',
  `is_editable` tinyint(1) DEFAULT '1' COMMENT 'Can be edited via UI',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `is_editable`, `created_at`, `updated_at`) VALUES
(1, 'import_throttle_enabled', 'false', 'boolean', 'Enable import throttling to prevent server overload', 1, '2025-11-08 15:26:49', '2025-11-08 15:26:49'),
(2, 'import_throttle_batch_size', '100', 'integer', 'Number of rows to process before throttling pause', 1, '2025-11-08 15:26:49', '2025-11-08 15:26:49'),
(3, 'import_throttle_delay_ms', '100', 'integer', 'Delay in milliseconds between batches', 1, '2025-11-08 15:26:49', '2025-11-08 15:26:49'),
(4, 'import_max_execution_time', '300', 'integer', 'Maximum execution time for imports in seconds', 1, '2025-11-08 15:26:49', '2025-11-08 15:26:49'),
(5, 'import_max_memory_mb', '256', 'integer', 'Maximum memory allocation for imports in MB', 1, '2025-11-08 15:26:49', '2025-11-08 15:26:49');

-- --------------------------------------------------------

--
-- Table structure for table `tariffs`
--

CREATE TABLE `tariffs` (
  `id` int UNSIGNED NOT NULL,
  `supplier_id` int UNSIGNED DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `energy_type` enum('electricity','gas') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tariff_type` enum('fixed','variable','time_of_use','dynamic') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'fixed',
  `unit_rate` decimal(10,4) DEFAULT NULL COMMENT 'Pence per kWh',
  `standing_charge` decimal(10,4) DEFAULT NULL COMMENT 'Pence per day',
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `peak_rate` decimal(10,4) DEFAULT NULL,
  `off_peak_rate` decimal(10,4) DEFAULT NULL,
  `weekend_rate` decimal(10,4) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tariffs`
--

INSERT INTO `tariffs` (`id`, `supplier_id`, `name`, `code`, `energy_type`, `tariff_type`, `unit_rate`, `standing_charge`, `valid_from`, `valid_to`, `peak_rate`, `off_peak_rate`, `weekend_rate`, `is_active`, `created_at`, `updated_at`) VALUES
(1, NULL, 'British Gas Standard Variable (Oct-Dec 2024)', 'BG-SVT-Q42024', 'electricity', 'variable', 24.5000, 60.9900, '2024-10-01', '2024-12-31', NULL, NULL, NULL, 1, '2025-11-08 11:43:54', '2025-11-08 11:43:54'),
(2, NULL, 'British Gas Gas Standard (Oct-Dec 2024)', 'BG-GAS-Q42024', 'gas', 'variable', 6.2400, 31.6600, '2024-10-01', '2024-12-31', NULL, NULL, NULL, 1, '2025-11-08 11:43:54', '2025-11-08 11:43:54'),
(3, NULL, 'EDF Energy Standard Variable (Oct-Dec 2024)', 'EDF-SVT-Q42024', 'electricity', 'variable', 24.5000, 61.0000, '2024-10-01', '2024-12-31', NULL, NULL, NULL, 1, '2025-11-08 11:43:54', '2025-11-08 11:43:54'),
(4, NULL, 'EDF Energy Gas Standard (Oct-Dec 2024)', 'EDF-GAS-Q42024', 'gas', 'variable', 6.2000, 32.0000, '2024-10-01', '2024-12-31', NULL, NULL, NULL, 1, '2025-11-08 11:43:54', '2025-11-08 11:43:54'),
(5, NULL, 'Octopus Flexible (Oct-Dec 2024)', 'OCT-FLEX-Q42024', 'electricity', 'variable', 24.0000, 50.0000, '2024-10-01', '2024-12-31', NULL, NULL, NULL, 1, '2025-11-08 11:43:54', '2025-11-08 11:43:54'),
(6, NULL, 'Octopus Gas Flexible (Oct-Dec 2024)', 'OCT-GAS-Q42024', 'gas', 'variable', 6.1000, 31.0000, '2024-10-01', '2024-12-31', NULL, NULL, NULL, 1, '2025-11-08 11:43:54', '2025-11-08 11:43:54'),
(7, 1, 'OVO Standard Variable (Oct-Dec 2024)', 'OVO-SVT-Q42024', 'electricity', 'variable', 24.5000, 53.0000, '2024-10-01', '2024-12-31', NULL, NULL, NULL, 1, '2025-11-08 11:43:54', '2025-11-08 11:43:54'),
(8, 1, 'OVO Gas Standard (Oct-Dec 2024)', 'OVO-GAS-Q42024', 'gas', 'variable', 6.2000, 31.0000, '2024-10-01', '2024-12-31', NULL, NULL, NULL, 1, '2025-11-08 11:43:54', '2025-11-08 11:43:54'),
(9, 1, 'Standard Variable Electricity', 'BG-SVT-ELEC-01', 'electricity', 'variable', 26.3500, 53.6800, '2024-10-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(10, 1, 'Fixed Tariff v81 12M', 'BG-FIX12-V81', 'electricity', 'fixed', 25.0000, 50.0000, '2025-01-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(11, 1, 'Electric Driver v16', 'BG-EV-V16', 'electricity', 'time_of_use', NULL, 52.0000, '2025-01-01', NULL, 28.0000, 12.5000, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(12, 1, 'Economy 7 Electricity', 'BG-E7-01', 'electricity', 'time_of_use', NULL, 54.0000, '2024-01-01', NULL, 35.0000, 15.0000, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(13, 1, 'Fixed 24 Months Electricity', 'BG-FIX24-01', 'electricity', 'fixed', 24.5000, 48.0000, '2025-01-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(14, 1, 'Standard Variable Gas', 'BG-SVT-GAS-01', 'gas', 'variable', 6.2400, 31.0000, '2024-10-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(15, 2, 'Standard Variable Electricity', 'EDF-STD-01', 'electricity', 'variable', 26.5000, 49.0000, '2024-10-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(16, 2, 'Fixed 12 Months v5', 'EDF-FIX12-V5', 'electricity', 'fixed', 25.0000, 47.0000, '2025-01-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(17, 2, 'Fixed 24 Months', 'EDF-FIX24-01', 'electricity', 'fixed', 24.8000, 46.0000, '2025-01-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(18, 2, 'GoElectric 35', 'EDF-GO35-01', 'electricity', 'time_of_use', NULL, 48.0000, '2025-01-01', NULL, 32.0000, 13.5000, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(19, 2, 'Blue+ Price Promise', 'EDF-BLUE-01', 'electricity', 'variable', 26.0000, 49.0000, '2025-01-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(20, 2, 'Green Electricity', 'EDF-GREEN-01', 'electricity', 'variable', 26.8000, 50.0000, '2025-01-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(21, 3, 'Next Pledge Tracker', 'EON-PLEDGE-01', 'electricity', 'variable', 26.4000, 56.0000, '2024-10-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(22, 3, 'Next Drive v9', 'EON-DRIVE-V9', 'electricity', 'time_of_use', NULL, 55.0000, '2025-01-01', NULL, 28.5000, 6.7000, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(23, 3, 'Fixed 1 Year v12', 'EON-FIX1-V12', 'electricity', 'fixed', 25.2000, 54.0000, '2025-01-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(24, 3, 'Fixed 2 Year', 'EON-FIX2-01', 'electricity', 'fixed', 24.9000, 52.0000, '2025-01-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(25, 4, 'Standard Price Cap', 'SP-STD-CAP-01', 'electricity', 'variable', 26.6000, 60.0000, '2024-10-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(26, 4, 'Fixed 1 Year', 'SP-FIX1-01', 'electricity', 'fixed', 25.5000, 58.0000, '2025-01-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(27, 4, 'Fixed 2 Year', 'SP-FIX2-01', 'electricity', 'fixed', 25.0000, 56.0000, '2025-01-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(28, 4, 'Fixed 3 Year', 'SP-FIX3-01', 'electricity', 'fixed', 24.8000, 55.0000, '2025-01-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(29, 4, 'EV Optimise', 'SP-EV-OPT-01', 'electricity', 'time_of_use', NULL, 57.0000, '2025-01-01', NULL, 29.0000, 8.0000, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(30, 5, 'Flexible Octopus', 'OCT-FLEX-01', 'electricity', 'variable', 24.5000, 45.0000, '2024-10-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(31, 5, 'Octopus 12M Fixed v6', 'OCT-FIX12-V6', 'electricity', 'fixed', 24.0000, 44.0000, '2025-01-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(32, 5, 'Intelligent Octopus Go', 'OCT-GO-01', 'electricity', 'time_of_use', NULL, 47.0000, '2025-01-01', NULL, 30.0000, 7.5000, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(33, 5, 'Agile Octopus', 'OCT-AGILE-01', 'electricity', 'dynamic', NULL, 46.0000, '2024-01-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(34, 5, 'Octopus Tracker', 'OCT-TRACKER-01', 'electricity', 'variable', 23.7000, 45.5000, '2025-01-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(35, 6, 'Standard Variable', 'OVO-STD-01', 'electricity', 'variable', 26.4000, 58.0000, '2024-10-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(36, 6, '1 Year Fixed 24', 'OVO-FIX1-24', 'electricity', 'fixed', 25.1000, 56.0000, '2025-01-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(37, 6, 'Charge Anytime', 'OVO-EV-ANYTIME', 'electricity', 'time_of_use', NULL, 57.0000, '2025-01-01', NULL, 30.5000, 9.0000, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(38, 6, 'Zero Carbon', 'OVO-ZERO-01', 'electricity', 'variable', 26.8000, 59.0000, '2025-01-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(39, 7, 'Club Tariff', 'UW-CLUB-01', 'electricity', 'variable', 26.0000, 47.0000, '2024-10-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(40, 7, 'Fixed 12 Months', 'UW-FIX12-01', 'electricity', 'fixed', 25.0000, 45.0000, '2025-01-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(41, 8, 'Standard Variable', 'SSE-STD-01', 'electricity', 'variable', 26.7000, 61.0000, '2024-10-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(42, 8, 'Fixed 1 Year v8', 'SSE-FIX1-V8', 'electricity', 'fixed', 25.8000, 60.0000, '2025-01-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(43, 9, 'Smart PAYG No Standing Charge', 'UTL-PAYG-NSC', 'electricity', 'variable', 52.5500, 0.0000, '2025-03-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(44, 9, 'Standard PAYG', 'UTL-PAYG-STD', 'electricity', 'variable', 25.5400, 53.6800, '2024-10-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(45, 10, 'Fixed 12 Months', 'SHELL-FIX12-01', 'electricity', 'fixed', 25.3000, 51.0000, '2025-01-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(46, 10, 'Variable', 'SHELL-VAR-01', 'electricity', 'variable', 26.2000, 52.0000, '2024-10-01', NULL, NULL, NULL, NULL, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37');

-- --------------------------------------------------------

--
-- Table structure for table `tariff_switching_analyses`
--

CREATE TABLE `tariff_switching_analyses` (
  `id` int UNSIGNED NOT NULL,
  `meter_id` int UNSIGNED NOT NULL,
  `analysis_date` date NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `current_tariff_id` int UNSIGNED NOT NULL,
  `recommended_tariff_id` int UNSIGNED DEFAULT NULL,
  `current_cost` decimal(10,2) DEFAULT NULL,
  `recommended_cost` decimal(10,2) DEFAULT NULL,
  `potential_savings` decimal(10,2) DEFAULT NULL,
  `total_consumption` decimal(12,4) DEFAULT NULL,
  `analysis_data` json DEFAULT NULL,
  `created_by` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','manager','viewer') COLLATE utf8mb4_unicode_ci DEFAULT 'viewer',
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `name`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin@eclectyc.energy', '$2y$10$X5JYrG2tdLhfZ4qZ8GDkEu8wQZWMk8TEpefhxE7QzaL5VhRkTW032', 'System Admin', 'admin', 1, '2025-11-09 10:33:38', '2025-11-06 17:55:31', '2025-11-09 09:33:38'),
(5, 'manager@eclectyc.energy', '$2y$12$rgMQ5sK8qJJdPYjc9tZjy.cYKCO.z9S1gJqdi//WhmLsxOVHNnx5G', 'Operations Manager', 'manager', 1, '2025-11-07 14:52:28', '2025-11-06 18:14:10', '2025-11-07 13:52:28'),
(6, 'viewer@eclectyc.energy', '$2y$12$BHs/mHuXtOKniznnTc6I.O7WtIeR7ikdss7VHP.Oh1UvJu6ZY7Cae', 'Read Only Analyst', 'viewer', 1, NULL, '2025-11-06 18:14:10', '2025-11-07 13:51:33');

-- --------------------------------------------------------

--
-- Table structure for table `weekly_aggregations`
--

CREATE TABLE `weekly_aggregations` (
  `id` bigint UNSIGNED NOT NULL,
  `meter_id` int UNSIGNED NOT NULL,
  `week_start` date NOT NULL,
  `week_end` date NOT NULL,
  `total_consumption` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `peak_consumption` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `off_peak_consumption` decimal(12,4) NOT NULL DEFAULT '0.0000',
  `min_daily_consumption` decimal(12,4) DEFAULT NULL,
  `max_daily_consumption` decimal(12,4) DEFAULT NULL,
  `day_count` int NOT NULL DEFAULT '0',
  `reading_count` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `weekly_aggregations`
--

INSERT INTO `weekly_aggregations` (`id`, `meter_id`, `week_start`, `week_end`, `total_consumption`, `peak_consumption`, `off_peak_consumption`, `min_daily_consumption`, `max_daily_consumption`, `day_count`, `reading_count`, `created_at`, `updated_at`) VALUES
(1, 1, '2025-10-27', '2025-11-02', 411.8000, 0.0000, 411.8000, 95.3000, 110.2000, 4, 4, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(2, 1, '2025-11-03', '2025-11-09', 402.0000, 0.0000, 402.0000, 98.6000, 102.4000, 4, 4, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(3, 3, '2025-10-27', '2025-11-02', 1816.8000, 0.0000, 1816.8000, 425.8000, 478.3000, 4, 4, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(4, 3, '2025-11-03', '2025-11-09', 1798.4000, 0.0000, 1798.4000, 441.9000, 455.6000, 4, 4, '2025-11-08 13:00:37', '2025-11-08 13:00:37');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ai_insights`
--
ALTER TABLE `ai_insights`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dismissed_by` (`dismissed_by`),
  ADD KEY `idx_meter` (`meter_id`),
  ADD KEY `idx_date` (`insight_date`),
  ADD KEY `idx_type` (`insight_type`),
  ADD KEY `idx_dismissed` (`is_dismissed`);

--
-- Indexes for table `annual_aggregations`
--
ALTER TABLE `annual_aggregations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_meter_year` (`meter_id`,`year`),
  ADD KEY `idx_year` (`year`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registration_number` (`registration_number`),
  ADD KEY `primary_contact_id` (`primary_contact_id`),
  ADD KEY `idx_registration` (`registration_number`);

--
-- Indexes for table `comparison_snapshots`
--
ALTER TABLE `comparison_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_snapshot` (`meter_id`,`snapshot_date`,`snapshot_type`),
  ADD KEY `idx_meter` (`meter_id`),
  ADD KEY `idx_date` (`snapshot_date`),
  ADD KEY `idx_type` (`snapshot_type`);

--
-- Indexes for table `daily_aggregations`
--
ALTER TABLE `daily_aggregations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_meter_date` (`meter_id`,`date`),
  ADD KEY `idx_date` (`date`);

--
-- Indexes for table `data_quality_issues`
--
ALTER TABLE `data_quality_issues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `meter_id` (`meter_id`),
  ADD KEY `resolved_by` (`resolved_by`),
  ADD KEY `idx_meter` (`meter_id`),
  ADD KEY `idx_issue_date` (`issue_date`),
  ADD KEY `idx_resolved` (`is_resolved`);

--
-- Indexes for table `exports`
--
ALTER TABLE `exports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `external_calorific_values`
--
ALTER TABLE `external_calorific_values`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_region_date` (`region`,`date`),
  ADD KEY `idx_region` (`region`),
  ADD KEY `idx_date` (`date`);

--
-- Indexes for table `external_carbon_intensity`
--
ALTER TABLE `external_carbon_intensity`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_region_datetime` (`region`,`datetime`),
  ADD KEY `idx_region` (`region`),
  ADD KEY `idx_datetime` (`datetime`);

--
-- Indexes for table `external_temperature_data`
--
ALTER TABLE `external_temperature_data`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_location_date` (`location`,`date`),
  ADD KEY `idx_location` (`location`),
  ADD KEY `idx_date` (`date`);

--
-- Indexes for table `import_jobs`
--
ALTER TABLE `import_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `batch_id` (`batch_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `parent_job_id` (`parent_job_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_queued_at` (`queued_at`),
  ADD KEY `idx_default_site` (`default_site_id`),
  ADD KEY `idx_default_tariff` (`default_tariff_id`);

--
-- Indexes for table `meters`
--
ALTER TABLE `meters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mpan` (`mpan`),
  ADD KEY `site_id` (`site_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `idx_meter_type` (`meter_type`),
  ADD KEY `idx_half_hourly` (`is_half_hourly`),
  ADD KEY `idx_batch_id` (`batch_id`);

--
-- Indexes for table `meter_readings`
--
ALTER TABLE `meter_readings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_meter_datetime` (`meter_id`,`reading_date`,`reading_time`),
  ADD UNIQUE KEY `unique_reading` (`meter_id`,`reading_date`,`reading_time`,`period_number`),
  ADD KEY `idx_meter_date` (`meter_id`,`reading_date`),
  ADD KEY `idx_date` (`reading_date`),
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_batch` (`import_batch_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `monthly_aggregations`
--
ALTER TABLE `monthly_aggregations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_meter_month` (`meter_id`,`month_start`),
  ADD KEY `idx_month_start` (`month_start`);

--
-- Indexes for table `regions`
--
ALTER TABLE `regions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `scheduler_alerts`
--
ALTER TABLE `scheduler_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_alert_type` (`alert_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `scheduler_executions`
--
ALTER TABLE `scheduler_executions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_range_type` (`range_type`),
  ADD KEY `idx_start_time` (`start_time`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_setting_key` (`setting_key`);

--
-- Indexes for table `sftp_configurations`
--
ALTER TABLE `sftp_configurations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_auto_import` (`auto_import`);

--
-- Indexes for table `sites`
--
ALTER TABLE `sites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `region_id` (`region_id`),
  ADD KEY `idx_postcode` (`postcode`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- Indexes for table `tariffs`
--
ALTER TABLE `tariffs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_dates` (`valid_from`,`valid_to`);

--
-- Indexes for table `tariff_switching_analyses`
--
ALTER TABLE `tariff_switching_analyses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `meter_id` (`meter_id`),
  ADD KEY `current_tariff_id` (`current_tariff_id`),
  ADD KEY `recommended_tariff_id` (`recommended_tariff_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_analysis_date` (`analysis_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `weekly_aggregations`
--
ALTER TABLE `weekly_aggregations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_meter_week` (`meter_id`,`week_start`),
  ADD KEY `idx_week_start` (`week_start`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ai_insights`
--
ALTER TABLE `ai_insights`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `annual_aggregations`
--
ALTER TABLE `annual_aggregations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `comparison_snapshots`
--
ALTER TABLE `comparison_snapshots`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_aggregations`
--
ALTER TABLE `daily_aggregations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `data_quality_issues`
--
ALTER TABLE `data_quality_issues`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exports`
--
ALTER TABLE `exports`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `external_calorific_values`
--
ALTER TABLE `external_calorific_values`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `external_carbon_intensity`
--
ALTER TABLE `external_carbon_intensity`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `external_temperature_data`
--
ALTER TABLE `external_temperature_data`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `import_jobs`
--
ALTER TABLE `import_jobs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `meters`
--
ALTER TABLE `meters`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `meter_readings`
--
ALTER TABLE `meter_readings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `monthly_aggregations`
--
ALTER TABLE `monthly_aggregations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `regions`
--
ALTER TABLE `regions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `scheduler_alerts`
--
ALTER TABLE `scheduler_alerts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scheduler_executions`
--
ALTER TABLE `scheduler_executions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sftp_configurations`
--
ALTER TABLE `sftp_configurations`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sites`
--
ALTER TABLE `sites`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tariffs`
--
ALTER TABLE `tariffs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `tariff_switching_analyses`
--
ALTER TABLE `tariff_switching_analyses`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `weekly_aggregations`
--
ALTER TABLE `weekly_aggregations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ai_insights`
--
ALTER TABLE `ai_insights`
  ADD CONSTRAINT `ai_insights_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ai_insights_ibfk_2` FOREIGN KEY (`dismissed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `annual_aggregations`
--
ALTER TABLE `annual_aggregations`
  ADD CONSTRAINT `annual_aggregations_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `companies`
--
ALTER TABLE `companies`
  ADD CONSTRAINT `companies_ibfk_1` FOREIGN KEY (`primary_contact_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `comparison_snapshots`
--
ALTER TABLE `comparison_snapshots`
  ADD CONSTRAINT `comparison_snapshots_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `daily_aggregations`
--
ALTER TABLE `daily_aggregations`
  ADD CONSTRAINT `daily_aggregations_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `data_quality_issues`
--
ALTER TABLE `data_quality_issues`
  ADD CONSTRAINT `data_quality_issues_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `data_quality_issues_ibfk_2` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `exports`
--
ALTER TABLE `exports`
  ADD CONSTRAINT `exports_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `import_jobs`
--
ALTER TABLE `import_jobs`
  ADD CONSTRAINT `fk_import_jobs_site` FOREIGN KEY (`default_site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_import_jobs_tariff` FOREIGN KEY (`default_tariff_id`) REFERENCES `tariffs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `import_jobs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `import_jobs_ibfk_2` FOREIGN KEY (`parent_job_id`) REFERENCES `import_jobs` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `meters`
--
ALTER TABLE `meters`
  ADD CONSTRAINT `meters_ibfk_1` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `meters_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `meter_readings`
--
ALTER TABLE `meter_readings`
  ADD CONSTRAINT `meter_readings_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `monthly_aggregations`
--
ALTER TABLE `monthly_aggregations`
  ADD CONSTRAINT `monthly_aggregations_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sites`
--
ALTER TABLE `sites`
  ADD CONSTRAINT `sites_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `sites_ibfk_2` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tariffs`
--
ALTER TABLE `tariffs`
  ADD CONSTRAINT `tariffs_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tariff_switching_analyses`
--
ALTER TABLE `tariff_switching_analyses`
  ADD CONSTRAINT `tariff_switching_analyses_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tariff_switching_analyses_ibfk_2` FOREIGN KEY (`current_tariff_id`) REFERENCES `tariffs` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `tariff_switching_analyses_ibfk_3` FOREIGN KEY (`recommended_tariff_id`) REFERENCES `tariffs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tariff_switching_analyses_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `weekly_aggregations`
--
ALTER TABLE `weekly_aggregations`
  ADD CONSTRAINT `weekly_aggregations_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
