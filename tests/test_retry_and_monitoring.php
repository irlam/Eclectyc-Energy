#!/usr/bin/env php
<?php
/**
 * eclectyc-energy/tests/test_retry_and_monitoring.php
 * Integration test for retry logic and monitoring services
 * Last updated: 2025-11-07
 */

use App\Config\Database;
use App\Domain\Ingestion\ImportJobService;
use App\Domain\Ingestion\ImportMonitoringService;
use App\Domain\Ingestion\ImportAlertService;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/London');

echo "\n";
echo "===========================================\n";
echo "  Retry Logic & Monitoring Tests\n";
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

// Test 1: Check class loading
test("ImportJobService class exists", function() {
    return class_exists('App\Domain\Ingestion\ImportJobService');
});

test("ImportMonitoringService class exists", function() {
    return class_exists('App\Domain\Ingestion\ImportMonitoringService');
});

test("ImportAlertService class exists", function() {
    return class_exists('App\Domain\Ingestion\ImportAlertService');
});

// Test 2: Database connection
test("Database connection", function() {
    $pdo = Database::getConnection();
    return $pdo !== null;
});

// Test 3: Check migration applied
test("Import jobs table has retry fields", function() {
    $pdo = Database::getConnection();
    $stmt = $pdo->query("SHOW COLUMNS FROM import_jobs LIKE 'retry_count'");
    return $stmt->rowCount() > 0;
});

test("Import jobs table has attribution fields", function() {
    $pdo = Database::getConnection();
    $stmt = $pdo->query("SHOW COLUMNS FROM import_jobs LIKE 'notes'");
    $hasNotes = $stmt->rowCount() > 0;
    $stmt = $pdo->query("SHOW COLUMNS FROM import_jobs LIKE 'priority'");
    $hasPriority = $stmt->rowCount() > 0;
    $stmt = $pdo->query("SHOW COLUMNS FROM import_jobs LIKE 'tags'");
    $hasTags = $stmt->rowCount() > 0;
    return $hasNotes && $hasPriority && $hasTags;
});

test("Import jobs table has alert fields", function() {
    $pdo = Database::getConnection();
    $stmt = $pdo->query("SHOW COLUMNS FROM import_jobs LIKE 'alert_sent'");
    return $stmt->rowCount() > 0;
});

// Test 4: Service instantiation
test("ImportJobService can be instantiated", function() {
    $pdo = Database::getConnection();
    $service = new ImportJobService($pdo);
    return $service instanceof ImportJobService;
});

test("ImportMonitoringService can be instantiated", function() {
    $pdo = Database::getConnection();
    $service = new ImportMonitoringService($pdo);
    return $service instanceof ImportMonitoringService;
});

test("ImportAlertService can be instantiated", function() {
    $pdo = Database::getConnection();
    $service = new ImportAlertService($pdo);
    return $service instanceof ImportAlertService;
});

// Test 5: Check ImportJobService methods
test("ImportJobService has createJob method", function() {
    return method_exists('App\Domain\Ingestion\ImportJobService', 'createJob');
});

test("ImportJobService has retryJob method", function() {
    return method_exists('App\Domain\Ingestion\ImportJobService', 'retryJob');
});

test("ImportJobService has canRetry method", function() {
    return method_exists('App\Domain\Ingestion\ImportJobService', 'canRetry');
});

test("ImportJobService has markAlertSent method", function() {
    return method_exists('App\Domain\Ingestion\ImportJobService', 'markAlertSent');
});

test("ImportJobService has getFailedJobsNeedingAlerts method", function() {
    return method_exists('App\Domain\Ingestion\ImportJobService', 'getFailedJobsNeedingAlerts');
});

// Test 6: Check ImportMonitoringService methods
test("ImportMonitoringService has getSystemHealth method", function() {
    return method_exists('App\Domain\Ingestion\ImportMonitoringService', 'getSystemHealth');
});

test("ImportMonitoringService has getStuckJobs method", function() {
    return method_exists('App\Domain\Ingestion\ImportMonitoringService', 'getStuckJobs');
});

test("ImportMonitoringService has getRecentFailureRate method", function() {
    return method_exists('App\Domain\Ingestion\ImportMonitoringService', 'getRecentFailureRate');
});

test("ImportMonitoringService has getRetryStatistics method", function() {
    return method_exists('App\Domain\Ingestion\ImportMonitoringService', 'getRetryStatistics');
});

test("ImportMonitoringService has handleStuckJobs method", function() {
    return method_exists('App\Domain\Ingestion\ImportMonitoringService', 'handleStuckJobs');
});

// Test 7: Check ImportAlertService methods
test("ImportAlertService has sendFailureAlert method", function() {
    return method_exists('App\Domain\Ingestion\ImportAlertService', 'sendFailureAlert');
});

test("ImportAlertService has sendBatchFailureAlert method", function() {
    return method_exists('App\Domain\Ingestion\ImportAlertService', 'sendBatchFailureAlert');
});

test("ImportAlertService has sendStuckJobsAlert method", function() {
    return method_exists('App\Domain\Ingestion\ImportAlertService', 'sendStuckJobsAlert');
});

// Test 8: Test monitoring service functionality
test("MonitoringService can get system health", function() {
    $pdo = Database::getConnection();
    $service = new ImportMonitoringService($pdo);
    $health = $service->getSystemHealth();
    return isset($health['status']) && isset($health['metrics']) && isset($health['alerts']);
});

test("MonitoringService returns valid health status", function() {
    $pdo = Database::getConnection();
    $service = new ImportMonitoringService($pdo);
    $health = $service->getSystemHealth();
    return in_array($health['status'], ['healthy', 'degraded', 'critical']);
});

test("MonitoringService can get queue depth", function() {
    $pdo = Database::getConnection();
    $service = new ImportMonitoringService($pdo);
    $depth = $service->getQueueDepth();
    return is_int($depth) && $depth >= 0;
});

test("MonitoringService can get performance metrics", function() {
    $pdo = Database::getConnection();
    $service = new ImportMonitoringService($pdo);
    $metrics = $service->getPerformanceMetrics();
    return is_array($metrics);
});

// Test 9: Check script files exist
test("monitor_import_system.php exists and is executable", function() {
    $path = dirname(__DIR__) . '/scripts/monitor_import_system.php';
    return file_exists($path) && is_executable($path);
});

test("cleanup_import_jobs.php exists and is executable", function() {
    $path = dirname(__DIR__) . '/scripts/cleanup_import_jobs.php';
    return file_exists($path) && is_executable($path);
});

// Test 10: Check deployment files exist
test("Supervisor config template exists", function() {
    $path = dirname(__DIR__) . '/deployment/supervisor-import-worker.conf';
    return file_exists($path);
});

test("Systemd config template exists", function() {
    $path = dirname(__DIR__) . '/deployment/systemd-import-worker.service';
    return file_exists($path);
});

test("Crontab example exists", function() {
    $path = dirname(__DIR__) . '/deployment/crontab.example';
    return file_exists($path);
});

// Test 11: Check documentation
test("Operationalizing documentation exists", function() {
    $path = dirname(__DIR__) . '/docs/operationalizing_async_systems.md';
    return file_exists($path) && filesize($path) > 1000;
});

// Test 12: Validate PHP syntax of new scripts
test("monitor_import_system.php has valid PHP syntax", function() {
    $path = dirname(__DIR__) . '/scripts/monitor_import_system.php';
    exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $returnCode);
    return $returnCode === 0;
});

test("cleanup_import_jobs.php has valid PHP syntax", function() {
    $path = dirname(__DIR__) . '/scripts/cleanup_import_jobs.php';
    exec("php -l " . escapeshellarg($path) . " 2>&1", $output, $returnCode);
    return $returnCode === 0;
});

// Test 13: Functional test - Create job with retry parameters
test("Can create job with retry and attribution parameters", function() {
    $pdo = Database::getConnection();
    $service = new ImportJobService($pdo);
    
    // Create a test file
    $testFile = sys_get_temp_dir() . '/test_' . uniqid() . '.csv';
    file_put_contents($testFile, "MPAN,Date,Reading\n1234567890123,2025-11-07,10.5\n");
    
    try {
        $batchId = $service->createJob(
            filename: 'test.csv',
            filePath: $testFile,
            importType: 'daily',
            userId: null,
            dryRun: true,
            notes: 'Test import',
            priority: 'high',
            tags: ['test', 'retry-test'],
            metadata: ['source' => 'test'],
            maxRetries: 5
        );
        
        // Verify job was created
        $job = $service->getJob($batchId);
        
        // Cleanup
        @unlink($testFile);
        $pdo->exec("DELETE FROM import_jobs WHERE batch_id = " . $pdo->quote($batchId));
        
        return $job !== null && 
               $job['notes'] === 'Test import' && 
               $job['priority'] === 'high' &&
               $job['max_retries'] == 5;
    } catch (Exception $e) {
        @unlink($testFile);
        throw $e;
    }
});

// Print summary
echo "\n";
echo "===========================================\n";
echo "  Test Summary\n";
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

exit($failed > 0 ? 1 : 0);
