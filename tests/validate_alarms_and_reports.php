#!/usr/bin/env php
<?php
/**
 * Test script to validate alarms and scheduled reports functionality
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Database;
use App\Models\Alarm;
use App\Models\ScheduledReport;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

echo "Testing Alarms and Scheduled Reports Functionality\n";
echo "===================================================\n\n";

try {
    // Get database connection
    $pdo = Database::getConnection();
    
    // Test 1: Check if alarm tables exist
    echo "Test 1: Checking alarm tables...\n";
    $tables = ['alarms', 'alarm_triggers', 'alarm_recipients'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "  ✓ Table '$table' exists\n";
        } else {
            echo "  ✗ Table '$table' NOT found - run migrations!\n";
        }
    }
    echo "\n";
    
    // Test 2: Check if scheduled report tables exist
    echo "Test 2: Checking scheduled report tables...\n";
    $tables = ['scheduled_reports', 'scheduled_report_recipients', 'report_executions'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "  ✓ Table '$table' exists\n";
        } else {
            echo "  ✗ Table '$table' NOT found - run migrations!\n";
        }
    }
    echo "\n";
    
    // Test 3: Check if tariffs table has company_id column
    echo "Test 3: Checking tariff access control...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM tariffs LIKE 'company_id'");
    if ($stmt->rowCount() > 0) {
        echo "  ✓ Column 'company_id' exists in tariffs table\n";
    } else {
        echo "  ✗ Column 'company_id' NOT found - run migration 015!\n";
    }
    echo "\n";
    
    // Test 4: Check if permissions exist
    echo "Test 4: Checking permissions...\n";
    $permissions = ['alarm.view', 'alarm.create', 'report.view', 'report.create', 'report.run'];
    foreach ($permissions as $permission) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM permissions WHERE name = ?");
        $stmt->execute([$permission]);
        $count = $stmt->fetchColumn();
        if ($count > 0) {
            echo "  ✓ Permission '$permission' exists\n";
        } else {
            echo "  ✗ Permission '$permission' NOT found - run migration 016!\n";
        }
    }
    echo "\n";
    
    // Test 5: Check if storage directory exists
    echo "Test 5: Checking storage directories...\n";
    $reportDir = __DIR__ . '/../storage/reports';
    if (is_dir($reportDir)) {
        echo "  ✓ Directory 'storage/reports' exists\n";
        if (is_writable($reportDir)) {
            echo "  ✓ Directory is writable\n";
        } else {
            echo "  ⚠ Directory is NOT writable - check permissions!\n";
        }
    } else {
        echo "  ✗ Directory 'storage/reports' NOT found - create it!\n";
    }
    echo "\n";
    
    // Test 6: Check if model classes can be loaded
    echo "Test 6: Checking model classes...\n";
    try {
        $alarmClass = new ReflectionClass('App\\Models\\Alarm');
        echo "  ✓ Alarm model loaded\n";
    } catch (Exception $e) {
        echo "  ✗ Alarm model failed to load: " . $e->getMessage() . "\n";
    }
    
    try {
        $reportClass = new ReflectionClass('App\\Models\\ScheduledReport');
        echo "  ✓ ScheduledReport model loaded\n";
    } catch (Exception $e) {
        echo "  ✗ ScheduledReport model failed to load: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    // Test 7: Check if service classes can be loaded
    echo "Test 7: Checking service classes...\n";
    try {
        $evalService = new ReflectionClass('App\\Domain\\Alarms\\AlarmEvaluationService');
        echo "  ✓ AlarmEvaluationService loaded\n";
    } catch (Exception $e) {
        echo "  ✗ AlarmEvaluationService failed to load: " . $e->getMessage() . "\n";
    }
    
    try {
        $reportService = new ReflectionClass('App\\Domain\\Reports\\ReportGenerationService');
        echo "  ✓ ReportGenerationService loaded\n";
    } catch (Exception $e) {
        echo "  ✗ ReportGenerationService failed to load: " . $e->getMessage() . "\n";
    }
    echo "\n";
    
    echo "===================================================\n";
    echo "Validation complete!\n";
    echo "\nNext steps:\n";
    echo "1. Run database migrations if any tables are missing\n";
    echo "2. Set up cron jobs for:\n";
    echo "   - php scripts/evaluate_alarms.php (daily)\n";
    echo "   - php scripts/process_scheduled_reports.php (hourly)\n";
    echo "3. Navigate to /admin/alarms and /admin/scheduled-reports\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
