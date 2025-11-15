#!/usr/bin/env php
<?php
/**
 * Simulates the cron scenario - run multiple instances rapidly
 * This test verifies that the lock prevents multiple instances
 */

echo "\n";
echo "Simulating Cron Scenario Test\n";
echo "==============================\n\n";

$scriptPath = dirname(__DIR__) . '/scripts/process_import_jobs.php';
$phpBin = PHP_BINARY;

// Clean up any existing lock files
$lockFile = dirname(__DIR__) . '/storage/process_import_jobs.lock';
$pidFile = dirname(__DIR__) . '/storage/process_import_jobs.pid';
@unlink($lockFile);
@unlink($pidFile);

echo "Test: Starting 5 instances rapidly (simulating overlapping cron runs)\n";
echo "Expected: Only one should run, others should detect lock and exit\n\n";

$pids = [];
$outputs = [];

// Start 5 instances rapidly
for ($i = 0; $i < 5; $i++) {
    $logFile = "/tmp/import_worker_test_$i.log";
    $command = "$phpBin $scriptPath --once > $logFile 2>&1 &";
    exec($command, $output, $returnCode);
    echo "Started instance #" . ($i + 1) . "\n";
    $outputs[$i] = $logFile;
    usleep(100000); // 0.1 second delay between starts
}

echo "\nWaiting for instances to complete (10 seconds)...\n";
sleep(10);

echo "\nAnalyzing results:\n";
echo "==================\n\n";

$actuallyRan = 0;
$blockedByLock = 0;

for ($i = 0; $i < 5; $i++) {
    $logFile = $outputs[$i];
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        
        if (strpos($content, 'Another instance is already running') !== false) {
            echo "Instance #" . ($i + 1) . ": ✓ Correctly blocked by lock\n";
            $blockedByLock++;
        } elseif (strpos($content, 'Import Job Processor') !== false) {
            echo "Instance #" . ($i + 1) . ": ✓ Acquired lock and ran\n";
            $actuallyRan++;
        } elseif (strpos($content, 'Failed to connect to database') !== false) {
            echo "Instance #" . ($i + 1) . ": ⚠ Ran but no database (expected in test environment)\n";
            $actuallyRan++;
        } elseif (empty(trim($content))) {
            echo "Instance #" . ($i + 1) . ": ? Empty log (may have been blocked silently)\n";
        } else {
            echo "Instance #" . ($i + 1) . ": ? Unexpected output\n";
            echo "  Content: " . substr($content, 0, 100) . "...\n";
        }
        
        // Clean up log file
        @unlink($logFile);
    } else {
        echo "Instance #" . ($i + 1) . ": ? Log file not found\n";
    }
}

// Clean up lock files
@unlink($lockFile);
@unlink($pidFile);

echo "\n";
echo "Summary:\n";
echo "========\n";
echo "Instances that ran: $actuallyRan\n";
echo "Instances blocked by lock: $blockedByLock\n";
echo "\n";

if ($actuallyRan <= 1 && $blockedByLock >= 3) {
    echo "✅ TEST PASSED: Lock mechanism is working correctly!\n";
    echo "   At most one instance ran, others were properly blocked.\n\n";
    exit(0);
} else {
    echo "❌ TEST FAILED: Multiple instances may have run simultaneously!\n";
    echo "   Expected: 1 running, 4 blocked\n";
    echo "   Actual: $actuallyRan running, $blockedByLock blocked\n\n";
    exit(1);
}
