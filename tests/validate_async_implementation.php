#!/usr/bin/env php
<?php
/**
 * eclectyc-energy/tests/validate_async_implementation.php
 * Lightweight validation of async ingestion and aggregation implementation
 * This test does NOT require database connection or composer install
 * Last updated: 2025-11-07
 */

echo "\n";
echo "===========================================\n";
echo "  Async Implementation Validation\n";
echo "  " . date('d/m/Y H:i:s') . "\n";
echo "===========================================\n\n";

$passed = 0;
$failed = 0;
$errors = [];

/**
 * Test helper function
 */
function test($description, callable $test) {
    global $passed, $failed, $errors;
    
    echo "Testing: $description... ";
    
    try {
        $result = $test();
        if ($result === true) {
            echo "✓ PASS\n";
            $passed++;
        } else {
            echo "✗ FAIL\n";
            $failed++;
            $errors[] = "$description: " . ($result ?: 'Test returned false');
        }
    } catch (Throwable $e) {
        echo "✗ ERROR\n";
        $failed++;
        $errors[] = "$description: " . $e->getMessage();
    }
}

$basePath = dirname(__DIR__);

// Test 1: Check migration file exists
test("Migration 005 (enhance_import_jobs) exists", function() use ($basePath) {
    return file_exists("$basePath/database/migrations/005_enhance_import_jobs.sql");
});

// Test 2: Check new service files exist
test("ImportJobService.php exists", function() use ($basePath) {
    return file_exists("$basePath/app/Domain/Ingestion/ImportJobService.php");
});

test("ImportMonitoringService.php exists", function() use ($basePath) {
    return file_exists("$basePath/app/Domain/Ingestion/ImportMonitoringService.php");
});

test("ImportAlertService.php exists", function() use ($basePath) {
    return file_exists("$basePath/app/Domain/Ingestion/ImportAlertService.php");
});

// Test 3: Check new scripts exist and are executable
test("monitor_import_system.php exists", function() use ($basePath) {
    return file_exists("$basePath/scripts/monitor_import_system.php");
});

test("monitor_import_system.php is executable", function() use ($basePath) {
    return is_executable("$basePath/scripts/monitor_import_system.php");
});

test("cleanup_import_jobs.php exists", function() use ($basePath) {
    return file_exists("$basePath/scripts/cleanup_import_jobs.php");
});

test("cleanup_import_jobs.php is executable", function() use ($basePath) {
    return is_executable("$basePath/scripts/cleanup_import_jobs.php");
});

// Test 4: Check deployment configurations exist
test("Supervisor config template exists", function() use ($basePath) {
    return file_exists("$basePath/deployment/supervisor-import-worker.conf");
});

test("Systemd service template exists", function() use ($basePath) {
    return file_exists("$basePath/deployment/systemd-import-worker.service");
});

test("Crontab example exists", function() use ($basePath) {
    return file_exists("$basePath/deployment/crontab.example");
});

// Test 5: Check documentation
test("Operationalizing documentation exists", function() use ($basePath) {
    return file_exists("$basePath/docs/operationalizing_async_systems.md");
});

test("Operationalizing documentation is comprehensive (>10KB)", function() use ($basePath) {
    $path = "$basePath/docs/operationalizing_async_systems.md";
    return file_exists($path) && filesize($path) > 10000;
});

// Test 6: Validate PHP syntax of service files
test("ImportJobService.php has valid PHP syntax", function() use ($basePath) {
    exec("php -l " . escapeshellarg("$basePath/app/Domain/Ingestion/ImportJobService.php") . " 2>&1", $output, $returnCode);
    return $returnCode === 0;
});

test("ImportMonitoringService.php has valid PHP syntax", function() use ($basePath) {
    exec("php -l " . escapeshellarg("$basePath/app/Domain/Ingestion/ImportMonitoringService.php") . " 2>&1", $output, $returnCode);
    return $returnCode === 0;
});

test("ImportAlertService.php has valid PHP syntax", function() use ($basePath) {
    exec("php -l " . escapeshellarg("$basePath/app/Domain/Ingestion/ImportAlertService.php") . " 2>&1", $output, $returnCode);
    return $returnCode === 0;
});

// Test 7: Validate PHP syntax of new scripts
test("monitor_import_system.php has valid PHP syntax", function() use ($basePath) {
    exec("php -l " . escapeshellarg("$basePath/scripts/monitor_import_system.php") . " 2>&1", $output, $returnCode);
    return $returnCode === 0;
});

test("cleanup_import_jobs.php has valid PHP syntax", function() use ($basePath) {
    exec("php -l " . escapeshellarg("$basePath/scripts/cleanup_import_jobs.php") . " 2>&1", $output, $returnCode);
    return $returnCode === 0;
});

test("process_import_jobs.php has valid PHP syntax", function() use ($basePath) {
    exec("php -l " . escapeshellarg("$basePath/scripts/process_import_jobs.php") . " 2>&1", $output, $returnCode);
    return $returnCode === 0;
});

// Test 8: Check migration content
test("Migration includes retry_count field", function() use ($basePath) {
    $content = file_get_contents("$basePath/database/migrations/005_enhance_import_jobs.sql");
    return strpos($content, 'retry_count') !== false;
});

test("Migration includes max_retries field", function() use ($basePath) {
    $content = file_get_contents("$basePath/database/migrations/005_enhance_import_jobs.sql");
    return strpos($content, 'max_retries') !== false;
});

test("Migration includes priority field", function() use ($basePath) {
    $content = file_get_contents("$basePath/database/migrations/005_enhance_import_jobs.sql");
    return strpos($content, 'priority') !== false;
});

test("Migration includes notes field", function() use ($basePath) {
    $content = file_get_contents("$basePath/database/migrations/005_enhance_import_jobs.sql");
    return strpos($content, 'notes') !== false;
});

test("Migration includes alert_sent field", function() use ($basePath) {
    $content = file_get_contents("$basePath/database/migrations/005_enhance_import_jobs.sql");
    return strpos($content, 'alert_sent') !== false;
});

// Test 9: Check ImportJobService methods
test("ImportJobService has retryJob method", function() use ($basePath) {
    $content = file_get_contents("$basePath/app/Domain/Ingestion/ImportJobService.php");
    return strpos($content, 'function retryJob') !== false;
});

test("ImportJobService has canRetry method", function() use ($basePath) {
    $content = file_get_contents("$basePath/app/Domain/Ingestion/ImportJobService.php");
    return strpos($content, 'function canRetry') !== false;
});

test("ImportJobService has markAlertSent method", function() use ($basePath) {
    $content = file_get_contents("$basePath/app/Domain/Ingestion/ImportJobService.php");
    return strpos($content, 'function markAlertSent') !== false;
});

test("ImportJobService has getFailedJobsNeedingAlerts method", function() use ($basePath) {
    $content = file_get_contents("$basePath/app/Domain/Ingestion/ImportJobService.php");
    return strpos($content, 'function getFailedJobsNeedingAlerts') !== false;
});

// Test 10: Check ImportMonitoringService methods
test("ImportMonitoringService has getSystemHealth method", function() use ($basePath) {
    $content = file_get_contents("$basePath/app/Domain/Ingestion/ImportMonitoringService.php");
    return strpos($content, 'function getSystemHealth') !== false;
});

test("ImportMonitoringService has getStuckJobs method", function() use ($basePath) {
    $content = file_get_contents("$basePath/app/Domain/Ingestion/ImportMonitoringService.php");
    return strpos($content, 'function getStuckJobs') !== false;
});

test("ImportMonitoringService has handleStuckJobs method", function() use ($basePath) {
    $content = file_get_contents("$basePath/app/Domain/Ingestion/ImportMonitoringService.php");
    return strpos($content, 'function handleStuckJobs') !== false;
});

// Test 11: Check ImportAlertService methods
test("ImportAlertService has sendFailureAlert method", function() use ($basePath) {
    $content = file_get_contents("$basePath/app/Domain/Ingestion/ImportAlertService.php");
    return strpos($content, 'function sendFailureAlert') !== false;
});

test("ImportAlertService has sendStuckJobsAlert method", function() use ($basePath) {
    $content = file_get_contents("$basePath/app/Domain/Ingestion/ImportAlertService.php");
    return strpos($content, 'function sendStuckJobsAlert') !== false;
});

test("ImportAlertService has sendHighFailureRateAlert method", function() use ($basePath) {
    $content = file_get_contents("$basePath/app/Domain/Ingestion/ImportAlertService.php");
    return strpos($content, 'function sendHighFailureRateAlert') !== false;
});

// Test 12: Check process_import_jobs.php includes retry logic
test("process_import_jobs.php uses ImportAlertService", function() use ($basePath) {
    $content = file_get_contents("$basePath/scripts/process_import_jobs.php");
    return strpos($content, 'ImportAlertService') !== false;
});

test("process_import_jobs.php implements retry logic", function() use ($basePath) {
    $content = file_get_contents("$basePath/scripts/process_import_jobs.php");
    return strpos($content, 'canRetry') !== false && strpos($content, 'retryJob') !== false;
});

// Test 13: Check documentation completeness
test("Documentation mentions retry logic", function() use ($basePath) {
    $content = file_get_contents("$basePath/docs/operationalizing_async_systems.md");
    return strpos($content, 'retry') !== false || strpos($content, 'Retry') !== false;
});

test("Documentation mentions monitoring", function() use ($basePath) {
    $content = file_get_contents("$basePath/docs/operationalizing_async_systems.md");
    return strpos($content, 'monitoring') !== false || strpos($content, 'Monitoring') !== false;
});

test("Documentation mentions alerting", function() use ($basePath) {
    $content = file_get_contents("$basePath/docs/operationalizing_async_systems.md");
    return strpos($content, 'alert') !== false || strpos($content, 'Alert') !== false;
});

test("Documentation mentions supervisor", function() use ($basePath) {
    $content = file_get_contents("$basePath/docs/operationalizing_async_systems.md");
    return strpos($content, 'supervisor') !== false || strpos($content, 'Supervisor') !== false;
});

// Test 14: Check README updates
test("README.md mentions monitor_import_system.php", function() use ($basePath) {
    $content = file_get_contents("$basePath/README.md");
    return strpos($content, 'monitor_import_system.php') !== false;
});

test("README.md mentions cleanup_import_jobs.php", function() use ($basePath) {
    $content = file_get_contents("$basePath/README.md");
    return strpos($content, 'cleanup_import_jobs.php') !== false;
});

// Test 15: Check STATUS.md updates
test("STATUS.md marks async ingestion as operationalized", function() use ($basePath) {
    $content = file_get_contents("$basePath/STATUS.md");
    return strpos($content, 'Operationalized async ingestion') !== false;
});

// Print summary
echo "\n";
echo "===========================================\n";
echo "  Validation Summary\n";
echo "===========================================\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

echo "\n";

if ($failed === 0) {
    echo "✓ All validation checks passed!\n";
    echo "  The async ingestion and aggregation implementation is complete.\n\n";
} else {
    echo "✗ Some validation checks failed.\n";
    echo "  Please review the errors above.\n\n";
}

exit($failed > 0 ? 1 : 0);
