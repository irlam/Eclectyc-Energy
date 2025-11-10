-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 10.35.233.124:3306
-- Generation Time: Nov 10, 2025 at 02:38 PM
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
(2, NULL, 'migration_007', 'tariffs', NULL, NULL, '{\"suppliers\": [\"British Gas\", \"EDF Energy\", \"Octopus Energy\", \"OVO Energy\"], \"description\": \"Added UK energy supplier tariffs for Q4 2024 based on Ofgem price cap\", \"valid_period\": \"October-December 2024\", \"tariffs_added\": 8}', '127.0.0.1', NULL, '2025-11-08 11:46:58', NULL, 0, NULL);

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
(2, 'Default Company', NULL, NULL, NULL, NULL, NULL, 1, '2025-11-08 13:28:23', '2025-11-08 13:28:23'),
(3, 'Green Energy Solutions', 'GES-001', NULL, '456 Renewable Street, Manchester, UK', NULL, NULL, 1, '2025-11-10 09:15:53', '2025-11-10 09:15:53');

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

--
-- Dumping data for table `external_carbon_intensity`
--

INSERT INTO `external_carbon_intensity` (`id`, `region`, `datetime`, `intensity`, `forecast`, `actual`, `source`, `created_at`, `updated_at`) VALUES
(1, 'GB', '2025-11-10 07:30:00', 130.00, 130.00, 129.00, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(2, 'GB', '2025-11-10 00:00:00', 85.00, 85.00, 87.00, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(3, 'GB', '2025-11-10 00:30:00', 83.00, 83.00, 85.00, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(4, 'GB', '2025-11-10 01:00:00', 84.00, 84.00, 82.00, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(5, 'GB', '2025-11-10 01:30:00', 82.00, 82.00, 82.00, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(6, 'GB', '2025-11-10 02:00:00', 82.00, 82.00, 81.00, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(7, 'GB', '2025-11-10 02:30:00', 82.00, 82.00, 81.00, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(8, 'GB', '2025-11-10 03:00:00', 79.00, 79.00, 80.00, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(9, 'GB', '2025-11-10 03:30:00', 79.00, 79.00, 82.00, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(10, 'GB', '2025-11-10 04:00:00', 79.00, 79.00, 84.00, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(11, 'GB', '2025-11-10 04:30:00', 83.00, 83.00, 89.00, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(12, 'GB', '2025-11-10 05:00:00', 83.00, 83.00, 92.00, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(13, 'GB', '2025-11-10 05:30:00', 87.00, 87.00, 94.00, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(14, 'GB', '2025-11-10 06:00:00', 75.00, 75.00, 106.00, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(15, 'GB', '2025-11-10 06:30:00', 103.00, 103.00, 123.00, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(16, 'GB', '2025-11-10 07:00:00', 115.00, 115.00, 129.00, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(18, 'GB', '2025-11-10 08:00:00', 133.00, 133.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(19, 'GB', '2025-11-10 08:30:00', 128.00, 128.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(20, 'GB', '2025-11-10 09:00:00', 128.00, 128.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(21, 'GB', '2025-11-10 09:30:00', 120.00, 120.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(22, 'GB', '2025-11-10 10:00:00', 109.00, 109.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(23, 'GB', '2025-11-10 10:30:00', 100.00, 100.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(24, 'GB', '2025-11-10 11:00:00', 101.00, 101.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(25, 'GB', '2025-11-10 11:30:00', 98.00, 98.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(26, 'GB', '2025-11-10 12:00:00', 107.00, 107.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(27, 'GB', '2025-11-10 12:30:00', 106.00, 106.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(28, 'GB', '2025-11-10 13:00:00', 111.00, 111.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(29, 'GB', '2025-11-10 13:30:00', 115.00, 115.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(30, 'GB', '2025-11-10 14:00:00', 125.00, 125.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(31, 'GB', '2025-11-10 14:30:00', 133.00, 133.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(32, 'GB', '2025-11-10 15:00:00', 145.00, 145.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(33, 'GB', '2025-11-10 15:30:00', 157.00, 157.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(34, 'GB', '2025-11-10 16:00:00', 174.00, 174.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(35, 'GB', '2025-11-10 16:30:00', 184.00, 184.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(36, 'GB', '2025-11-10 17:00:00', 187.00, 187.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(37, 'GB', '2025-11-10 17:30:00', 187.00, 187.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(38, 'GB', '2025-11-10 18:00:00', 187.00, 187.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(39, 'GB', '2025-11-10 18:30:00', 187.00, 187.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(40, 'GB', '2025-11-10 19:00:00', 182.00, 182.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(41, 'GB', '2025-11-10 19:30:00', 182.00, 182.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(42, 'GB', '2025-11-10 20:00:00', 176.00, 176.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(43, 'GB', '2025-11-10 20:30:00', 167.00, 167.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(44, 'GB', '2025-11-10 21:00:00', 155.00, 155.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(45, 'GB', '2025-11-10 21:30:00', 136.00, 136.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(46, 'GB', '2025-11-10 22:00:00', 119.00, 119.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(47, 'GB', '2025-11-10 22:30:00', 100.00, 100.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(48, 'GB', '2025-11-10 23:00:00', 94.00, 94.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30'),
(49, 'GB', '2025-11-10 23:30:00', 89.00, 89.00, NULL, 'national-grid-eso-api', '2025-11-10 08:02:27', '2025-11-10 08:17:30');

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
(4, 'd9769f3b-1ecf-4502-b6ab-136462b1da96', 'Test_HH_Data.csv', '/var/www/vhosts/hosting215226.ae97b.netcup.net/eclectyc.energy/httpdocs/app/storage/imports/1762776023_Test_HH_Data.csv', 'hh', 'queued', '', 0, NULL, 0, 0, 0, 1, '2025-11-10 12:00:23', NULL, NULL, NULL, 0, 0, 3, NULL, NULL, NULL, '8', NULL, NULL, NULL);

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
  `metric_variable_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Name of the metric variable (e.g., "Square Meters", "Beds", "Occupancy")',
  `metric_variable_value` decimal(15,3) DEFAULT NULL COMMENT 'Numeric value for the metric variable',
  `install_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `batch_id` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Batch ID of import that created this meter'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `meters`
--

INSERT INTO `meters` (`id`, `site_id`, `supplier_id`, `mpan`, `serial_number`, `meter_type`, `is_smart_meter`, `is_half_hourly`, `is_active`, `metric_variable_name`, `metric_variable_value`, `install_date`, `created_at`, `updated_at`, `batch_id`) VALUES
(1, 1, 1, '00-111-222-333-444', 'SM001', 'electricity', 1, 1, 1, NULL, NULL, NULL, '2025-11-08 13:00:37', '2025-11-08 13:00:37', NULL),
(2, 1, 1, '00-111-222-333-445', 'GM001', 'gas', 0, 0, 1, NULL, NULL, NULL, '2025-11-08 13:00:37', '2025-11-08 13:00:37', NULL),
(3, 2, 2, '00-222-333-444-555', 'SM002', 'electricity', 1, 1, 1, NULL, NULL, NULL, '2025-11-08 13:00:37', '2025-11-08 13:00:37', NULL),
(4, 3, 5, '00-333-444-555-666', 'SM003', 'electricity', 1, 1, 1, NULL, NULL, NULL, '2025-11-08 13:00:37', '2025-11-08 13:00:37', NULL),
(14, 4, NULL, 'E06BG12862', NULL, 'electricity', 0, 1, 1, NULL, NULL, NULL, '2025-11-10 11:46:11', '2025-11-10 11:46:11', NULL),
(15, 4, NULL, 'E07BG07453', NULL, 'electricity', 0, 1, 1, NULL, NULL, NULL, '2025-11-10 11:47:26', '2025-11-10 11:47:26', NULL),
(16, 4, NULL, 'E09BG14002', NULL, 'electricity', 0, 1, 1, NULL, NULL, NULL, '2025-11-10 11:48:42', '2025-11-10 11:48:42', NULL),
(17, 4, NULL, 'E09BG23735', NULL, 'electricity', 0, 1, 1, NULL, NULL, NULL, '2025-11-10 11:49:57', '2025-11-10 11:49:57', NULL),
(18, 4, NULL, 'E14ML01961', NULL, 'electricity', 0, 1, 1, NULL, NULL, NULL, '2025-11-10 11:51:13', '2025-11-10 11:51:13', NULL),
(19, 4, NULL, 'E18ML18100', NULL, 'electricity', 0, 1, 1, NULL, NULL, NULL, '2025-11-10 11:52:28', '2025-11-10 11:52:28', NULL),
(20, 4, NULL, 'E19ML13847', NULL, 'electricity', 0, 1, 1, NULL, NULL, NULL, '2025-11-10 11:53:43', '2025-11-10 11:53:43', NULL);

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
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Permission identifier (e.g., import.upload)',
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Human-readable permission name',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Description of what this permission allows',
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general' COMMENT 'Permission category for grouping',
  `is_active` tinyint(1) DEFAULT '1' COMMENT 'Whether this permission is currently active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `display_name`, `description`, `category`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'import.view', 'View Imports', 'Access to view import page and import history', 'imports', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(2, 'import.upload', 'Upload Import Files', 'Ability to upload and process CSV import files', 'imports', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(3, 'import.manage_jobs', 'Manage Import Jobs', 'Ability to view, cancel, and delete import jobs', 'imports', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(4, 'import.retry', 'Retry Failed Imports', 'Ability to retry failed import batches', 'imports', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(5, 'export.view', 'View Exports', 'Access to view export functionality', 'exports', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(6, 'export.create', 'Create Exports', 'Ability to create and download data exports', 'exports', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(7, 'users.view', 'View Users', 'Access to view user list', 'users', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(8, 'users.create', 'Create Users', 'Ability to create new user accounts', 'users', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(9, 'users.edit', 'Edit Users', 'Ability to edit existing user accounts', 'users', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(10, 'users.delete', 'Delete Users', 'Ability to delete user accounts', 'users', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(11, 'users.manage_permissions', 'Manage User Permissions', 'Ability to grant/revoke user permissions', 'users', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(12, 'meters.view', 'View Meters', 'Access to view meter list and details', 'meters', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(13, 'meters.create', 'Create Meters', 'Ability to create new meters', 'meters', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(14, 'meters.edit', 'Edit Meters', 'Ability to edit meter information', 'meters', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(15, 'meters.delete', 'Delete Meters', 'Ability to delete meters', 'meters', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(16, 'meters.view_carbon', 'View Carbon Intensity', 'Access to view meter carbon intensity data', 'meters', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(17, 'sites.view', 'View Sites', 'Access to view site list and details', 'sites', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(18, 'sites.create', 'Create Sites', 'Ability to create new sites', 'sites', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(19, 'sites.edit', 'Edit Sites', 'Ability to edit site information', 'sites', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(20, 'sites.delete', 'Delete Sites', 'Ability to delete sites', 'sites', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(21, 'tariffs.view', 'View Tariffs', 'Access to view tariff list and details', 'tariffs', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(22, 'tariffs.create', 'Create Tariffs', 'Ability to create new tariffs', 'tariffs', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(23, 'tariffs.edit', 'Edit Tariffs', 'Ability to edit tariff information', 'tariffs', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(24, 'tariffs.delete', 'Delete Tariffs', 'Ability to delete tariffs', 'tariffs', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(25, 'tariff_switching.view', 'View Tariff Analysis', 'Access to view tariff switching analysis', 'tariff_switching', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(26, 'tariff_switching.analyze', 'Perform Tariff Analysis', 'Ability to run tariff switching analysis', 'tariff_switching', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(27, 'tariff_switching.view_history', 'View Analysis History', 'Access to view historical tariff analyses', 'tariff_switching', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(28, 'reports.view', 'View Reports', 'Access to view reports section', 'reports', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(29, 'reports.consumption', 'View Consumption Reports', 'Access to consumption reports', 'reports', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(30, 'reports.costs', 'View Cost Reports', 'Access to cost reports', 'reports', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(31, 'settings.view', 'View Settings', 'Access to view system settings', 'settings', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(32, 'settings.edit', 'Edit Settings', 'Ability to modify system settings', 'settings', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(33, 'tools.view', 'View Tools', 'Access to view tools section', 'tools', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(34, 'tools.system_health', 'View System Health', 'Access to system health monitoring', 'tools', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(35, 'tools.sftp', 'Manage SFTP Configurations', 'Ability to manage SFTP configurations', 'tools', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(36, 'tools.logs', 'View System Logs', 'Access to view and clear system logs', 'tools', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31'),
(37, 'dashboard.view', 'View Dashboard', 'Access to view the main dashboard', 'general', 1, '2025-11-09 10:27:31', '2025-11-09 10:27:31');

-- --------------------------------------------------------

--
-- Table structure for table `regions`
--

CREATE TABLE `regions` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional description of the region',
  `country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'UK',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `regions`
--

INSERT INTO `regions` (`id`, `name`, `code`, `description`, `country`, `created_at`, `updated_at`) VALUES
(1, 'London', 'LON', NULL, 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(2, 'South East', 'SE', NULL, 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(3, 'South West', 'SW', NULL, 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(4, 'East of England', 'EE', NULL, 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(5, 'West Midlands', 'WM', NULL, 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(6, 'East Midlands', 'EM', NULL, 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(7, 'Yorkshire', 'YH', NULL, 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(8, 'North West', 'NW', NULL, 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(9, 'North East', 'NE', NULL, 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(10, 'Scotland', 'SCO', NULL, 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(11, 'Wales', 'WAL', NULL, 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(12, 'Northern Ireland', 'NI', NULL, 'UK', '2025-11-08 13:00:37', '2025-11-08 13:00:37');

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
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
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

INSERT INTO `sites` (`id`, `company_id`, `region_id`, `name`, `latitude`, `longitude`, `address`, `postcode`, `site_type`, `floor_area`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 8, 'Main Office', NULL, NULL, '123 Energy Street, Bolton, England', 'BL1 2AB', 'office', 500.00, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(2, 1, 8, 'Warehouse A', NULL, NULL, '456 Industrial Park, Manchester', 'M1 3BC', 'warehouse', 2000.00, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(3, 1, 1, 'London Branch', NULL, NULL, '789 Business Centre, London', 'SW1A 1AA', 'office', 300.00, 1, '2025-11-08 13:00:37', '2025-11-08 13:00:37'),
(4, 2, NULL, 'Auto-imported Meters', NULL, NULL, 'Auto-generated during CSV import', 'TBD', 'office', NULL, 1, '2025-11-08 13:28:23', '2025-11-08 13:28:23'),
(5, 1, 2, 'Canterbury - Thomas Becket', NULL, NULL, 'The Thomas Becket, 21-25 Best Lane, Canterbury CT1 2JB', 'CT1 2JB', 'retail', NULL, 1, '2025-11-10 09:17:16', '2025-11-10 09:17:16'),
(6, 1, 4, 'London - Hamilton Hall', NULL, NULL, 'The Hamilton Hall, Liverpool Street Station, London EC2M 7PY', 'EC2M 7PY', 'retail', NULL, 1, '2025-11-10 09:17:16', '2025-11-10 09:17:16'),
(7, 1, 4, 'London - Knights Templar', NULL, NULL, 'The Knights Templar, 95 Chancery Lane, London WC2A 1DT', 'WC2A 1DT', 'retail', NULL, 1, '2025-11-10 09:17:16', '2025-11-10 09:17:16'),
(8, 2, 1, 'Sara\'s House', NULL, NULL, '789 Residential Ave, Bolton BL2 1AA', 'BL2 1AA', 'residential', NULL, 1, '2025-11-10 09:17:16', '2025-11-10 11:43:48'),
(9, 2, 4, 'Pauls House', NULL, NULL, '321 London Road, London SW1A 1AA', 'SW1A 1AA', 'residential', NULL, 1, '2025-11-10 09:17:16', '2025-11-10 11:43:19'),
(10, 3, 3, 'Solar Farm Alpha', NULL, NULL, 'Green Fields, Birmingham B1 1AA', 'B1 1AA', 'industrial', NULL, 1, '2025-11-10 09:17:16', '2025-11-10 09:17:16');

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
(1, 'import_throttle_enabled', 'true', 'boolean', 'Enable throttling to avoid timeouts during large imports', 1, '2025-11-08 15:26:49', '2025-11-09 12:32:04'),
(2, 'import_throttle_batch_size', '25', 'integer', 'Number of rows processed per batch', 1, '2025-11-08 15:26:49', '2025-11-10 11:59:35'),
(3, 'import_throttle_delay_ms', '300', 'integer', 'Delay in milliseconds between batches', 1, '2025-11-08 15:26:49', '2025-11-10 11:59:35'),
(4, 'import_max_execution_time', '900', 'integer', 'Maximum script execution time in seconds for imports', 1, '2025-11-08 15:26:49', '2025-11-10 11:59:35'),
(5, 'import_max_memory_mb', '256', 'integer', 'Maximum memory in MB available during import', 1, '2025-11-08 15:26:49', '2025-11-09 12:14:42');

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
(1, 'admin@eclectyc.energy', '$2y$10$X5JYrG2tdLhfZ4qZ8GDkEu8wQZWMk8TEpefhxE7QzaL5VhRkTW032', 'System Admin', 'admin', 1, '2025-11-09 14:29:01', '2025-11-06 17:55:31', '2025-11-09 13:29:01'),
(5, 'manager@eclectyc.energy', '$2y$12$rgMQ5sK8qJJdPYjc9tZjy.cYKCO.z9S1gJqdi//WhmLsxOVHNnx5G', 'Operations Manager', 'manager', 1, '2025-11-07 14:52:28', '2025-11-06 18:14:10', '2025-11-07 13:52:28'),
(6, 'viewer@eclectyc.energy', '$2y$12$BHs/mHuXtOKniznnTc6I.O7WtIeR7ikdss7VHP.Oh1UvJu6ZY7Cae', 'Read Only Analyst', 'viewer', 1, '2025-11-09 14:07:41', '2025-11-06 18:14:10', '2025-11-09 13:07:41'),
(10, 'user1@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'User One', 'viewer', 1, NULL, '2025-11-10 09:17:16', '2025-11-10 09:17:16'),
(11, 'regional.manager@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Regional Manager NW', 'manager', 1, NULL, '2025-11-10 09:17:16', '2025-11-10 09:17:16'),
(12, 'energy.manager@jdw.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'JDW Energy Manager', 'manager', 1, NULL, '2025-11-10 09:17:16', '2025-11-10 09:17:16'),
(13, 'bolton.manager@jdw.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bolton Pub Manager', 'viewer', 1, NULL, '2025-11-10 09:17:16', '2025-11-10 09:17:16'),
(14, 'manager1@acme.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Portfolio Manager', 'manager', 1, NULL, '2025-11-10 09:17:16', '2025-11-10 09:17:16'),
(15, 'south.manager@jdw.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Southern Area Manager', 'manager', 1, NULL, '2025-11-10 09:17:16', '2025-11-10 09:17:16'),
(45, 'user45@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'User Forty Five', 'viewer', 1, NULL, '2025-11-10 09:17:16', '2025-11-10 09:17:16');

-- --------------------------------------------------------

--
-- Table structure for table `user_company_access`
--

CREATE TABLE `user_company_access` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `company_id` int UNSIGNED NOT NULL,
  `granted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `granted_by` int UNSIGNED DEFAULT NULL COMMENT 'User who granted this access'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores company-level access permissions for users';

--
-- Dumping data for table `user_company_access`
--

INSERT INTO `user_company_access` (`id`, `user_id`, `company_id`, `granted_at`, `granted_by`) VALUES
(1, 12, 1, '2025-11-10 09:17:16', NULL),
(2, 14, 2, '2025-11-10 09:17:16', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `permission_id` int UNSIGNED NOT NULL,
  `granted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `granted_by` int UNSIGNED DEFAULT NULL COMMENT 'User who granted this permission'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_permissions`
--

INSERT INTO `user_permissions` (`id`, `user_id`, `permission_id`, `granted_at`, `granted_by`) VALUES
(1, 1, 1, '2025-11-09 10:27:31', NULL),
(2, 1, 2, '2025-11-09 10:27:31', NULL),
(3, 1, 3, '2025-11-09 10:27:31', NULL),
(4, 1, 4, '2025-11-09 10:27:31', NULL),
(5, 1, 5, '2025-11-09 10:27:31', NULL),
(6, 1, 6, '2025-11-09 10:27:31', NULL),
(7, 1, 7, '2025-11-09 10:27:31', NULL),
(8, 1, 8, '2025-11-09 10:27:31', NULL),
(9, 1, 9, '2025-11-09 10:27:31', NULL),
(10, 1, 10, '2025-11-09 10:27:31', NULL),
(11, 1, 11, '2025-11-09 10:27:31', NULL),
(12, 1, 12, '2025-11-09 10:27:31', NULL),
(13, 1, 13, '2025-11-09 10:27:31', NULL),
(14, 1, 14, '2025-11-09 10:27:31', NULL),
(15, 1, 15, '2025-11-09 10:27:31', NULL),
(16, 1, 16, '2025-11-09 10:27:31', NULL),
(17, 1, 17, '2025-11-09 10:27:31', NULL),
(18, 1, 18, '2025-11-09 10:27:31', NULL),
(19, 1, 19, '2025-11-09 10:27:31', NULL),
(20, 1, 20, '2025-11-09 10:27:31', NULL),
(21, 1, 21, '2025-11-09 10:27:31', NULL),
(22, 1, 22, '2025-11-09 10:27:31', NULL),
(23, 1, 23, '2025-11-09 10:27:31', NULL),
(24, 1, 24, '2025-11-09 10:27:31', NULL),
(25, 1, 25, '2025-11-09 10:27:31', NULL),
(26, 1, 26, '2025-11-09 10:27:31', NULL),
(27, 1, 27, '2025-11-09 10:27:31', NULL),
(28, 1, 28, '2025-11-09 10:27:31', NULL),
(29, 1, 29, '2025-11-09 10:27:31', NULL),
(30, 1, 30, '2025-11-09 10:27:31', NULL),
(31, 1, 31, '2025-11-09 10:27:31', NULL),
(32, 1, 32, '2025-11-09 10:27:31', NULL),
(33, 1, 33, '2025-11-09 10:27:31', NULL),
(34, 1, 34, '2025-11-09 10:27:31', NULL),
(35, 1, 35, '2025-11-09 10:27:31', NULL),
(36, 1, 36, '2025-11-09 10:27:31', NULL),
(37, 1, 37, '2025-11-09 10:27:31', NULL),
(64, 6, 37, '2025-11-09 10:27:31', NULL),
(65, 6, 5, '2025-11-09 10:27:31', NULL),
(66, 6, 1, '2025-11-09 10:27:31', NULL),
(67, 6, 12, '2025-11-09 10:27:31', NULL),
(68, 6, 16, '2025-11-09 10:27:31', NULL),
(69, 6, 29, '2025-11-09 10:27:31', NULL),
(70, 6, 30, '2025-11-09 10:27:31', NULL),
(71, 6, 28, '2025-11-09 10:27:31', NULL),
(72, 6, 17, '2025-11-09 10:27:31', NULL),
(73, 6, 25, '2025-11-09 10:27:31', NULL),
(74, 6, 27, '2025-11-09 10:27:31', NULL),
(75, 6, 21, '2025-11-09 10:27:31', NULL),
(79, 5, 37, '2025-11-09 10:27:31', NULL),
(80, 5, 6, '2025-11-09 10:27:31', NULL),
(81, 5, 5, '2025-11-09 10:27:31', NULL),
(82, 5, 3, '2025-11-09 10:27:31', NULL),
(83, 5, 4, '2025-11-09 10:27:31', NULL),
(84, 5, 2, '2025-11-09 10:27:31', NULL),
(85, 5, 1, '2025-11-09 10:27:31', NULL),
(86, 5, 13, '2025-11-09 10:27:31', NULL),
(87, 5, 15, '2025-11-09 10:27:31', NULL),
(88, 5, 14, '2025-11-09 10:27:31', NULL),
(89, 5, 12, '2025-11-09 10:27:31', NULL),
(90, 5, 16, '2025-11-09 10:27:31', NULL),
(91, 5, 29, '2025-11-09 10:27:31', NULL),
(92, 5, 30, '2025-11-09 10:27:31', NULL),
(93, 5, 28, '2025-11-09 10:27:31', NULL),
(94, 5, 31, '2025-11-09 10:27:31', NULL),
(95, 5, 18, '2025-11-09 10:27:31', NULL),
(96, 5, 20, '2025-11-09 10:27:31', NULL),
(97, 5, 19, '2025-11-09 10:27:31', NULL),
(98, 5, 17, '2025-11-09 10:27:31', NULL),
(99, 5, 26, '2025-11-09 10:27:31', NULL),
(100, 5, 25, '2025-11-09 10:27:31', NULL),
(101, 5, 27, '2025-11-09 10:27:31', NULL),
(102, 5, 22, '2025-11-09 10:27:31', NULL),
(103, 5, 24, '2025-11-09 10:27:31', NULL),
(104, 5, 23, '2025-11-09 10:27:31', NULL),
(105, 5, 21, '2025-11-09 10:27:31', NULL),
(106, 5, 35, '2025-11-09 10:27:31', NULL),
(107, 5, 34, '2025-11-09 10:27:31', NULL),
(108, 5, 33, '2025-11-09 10:27:31', NULL),
(109, 5, 8, '2025-11-09 10:27:31', NULL),
(110, 5, 9, '2025-11-09 10:27:31', NULL),
(111, 5, 7, '2025-11-09 10:27:31', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_region_access`
--

CREATE TABLE `user_region_access` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `region_id` int UNSIGNED NOT NULL,
  `granted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `granted_by` int UNSIGNED DEFAULT NULL COMMENT 'User who granted this access'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores region-level access permissions for users';

--
-- Dumping data for table `user_region_access`
--

INSERT INTO `user_region_access` (`id`, `user_id`, `region_id`, `granted_at`, `granted_by`) VALUES
(1, 11, 1, '2025-11-10 09:17:16', NULL),
(2, 15, 2, '2025-11-10 09:17:16', NULL),
(3, 15, 4, '2025-11-10 09:17:16', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_site_access`
--

CREATE TABLE `user_site_access` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `site_id` int UNSIGNED NOT NULL,
  `granted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `granted_by` int UNSIGNED DEFAULT NULL COMMENT 'User who granted this access'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores site-level access permissions for users';

--
-- Dumping data for table `user_site_access`
--

INSERT INTO `user_site_access` (`id`, `user_id`, `site_id`, `granted_at`, `granted_by`) VALUES
(1, 10, 8, '2025-11-10 09:17:16', NULL),
(2, 45, 9, '2025-11-10 09:17:16', NULL),
(3, 13, 1, '2025-11-10 09:17:16', NULL);

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
  ADD KEY `idx_batch_id` (`batch_id`),
  ADD KEY `idx_metric_variable` (`metric_variable_name`);

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
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_active` (`is_active`);

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
-- Indexes for table `user_company_access`
--
ALTER TABLE `user_company_access`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_company` (`user_id`,`company_id`),
  ADD KEY `granted_by` (`granted_by`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_company` (`company_id`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_permission` (`user_id`,`permission_id`),
  ADD KEY `granted_by` (`granted_by`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_permission` (`permission_id`);

--
-- Indexes for table `user_region_access`
--
ALTER TABLE `user_region_access`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_region` (`user_id`,`region_id`),
  ADD KEY `granted_by` (`granted_by`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_region` (`region_id`);

--
-- Indexes for table `user_site_access`
--
ALTER TABLE `user_site_access`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_site` (`user_id`,`site_id`),
  ADD KEY `granted_by` (`granted_by`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_site` (`site_id`);

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
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=99;

--
-- AUTO_INCREMENT for table `external_temperature_data`
--
ALTER TABLE `external_temperature_data`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `import_jobs`
--
ALTER TABLE `import_jobs`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `meters`
--
ALTER TABLE `meters`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

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
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

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
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

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
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `user_company_access`
--
ALTER TABLE `user_company_access`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=142;

--
-- AUTO_INCREMENT for table `user_region_access`
--
ALTER TABLE `user_region_access`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_site_access`
--
ALTER TABLE `user_site_access`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
-- Constraints for table `user_company_access`
--
ALTER TABLE `user_company_access`
  ADD CONSTRAINT `user_company_access_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_company_access_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_company_access_ibfk_3` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_3` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_region_access`
--
ALTER TABLE `user_region_access`
  ADD CONSTRAINT `user_region_access_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_region_access_ibfk_2` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_region_access_ibfk_3` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_site_access`
--
ALTER TABLE `user_site_access`
  ADD CONSTRAINT `user_site_access_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_site_access_ibfk_2` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_site_access_ibfk_3` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `weekly_aggregations`
--
ALTER TABLE `weekly_aggregations`
  ADD CONSTRAINT `weekly_aggregations_ibfk_1` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
