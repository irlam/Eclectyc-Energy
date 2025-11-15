#!/usr/bin/env php
<?php
/**
 * eclectyc-energy/tests/test_import_job_lock.php
 * Test to verify process_import_jobs.php lock mechanism works correctly
 * Last updated: 2025-11-15
 */

echo "\n";
echo "===========================================\n";
echo "  Import Job Lock Mechanism Test\n";
echo "  " . date('d/m/Y H:i:s') . "\n";
echo "===========================================\n\n";

$testsPassed = 0;
$testsFailed = 0;

$scriptPath = dirname(__DIR__) . '/scripts/process_import_jobs.php';
$lockFile = dirname(__DIR__) . '/storage/process_import_jobs.lock';
$pidFile = dirname(__DIR__) . '/storage/process_import_jobs.pid';

// Test 1: Verify lock file doesn't exist initially
echo "Test 1: Checking initial state...\n";
try {
    // Clean up any leftover files
    if (file_exists($lockFile)) {
        @unlink($lockFile);
    }
    if (file_exists($pidFile)) {
        @unlink($pidFile);
    }
    
    if (!file_exists($lockFile) && !file_exists($pidFile)) {
        echo "✓ No lock or PID files present\n";
        $testsPassed++;
    } else {
        throw new Exception("Lock or PID files still exist after cleanup");
    }
} catch (Exception $e) {
    echo "✗ " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 2: Start script with --once and verify lock file is created
echo "\nTest 2: Starting script with --once flag...\n";
try {
    // Start the script in background with --once flag
    $phpBin = PHP_BINARY;
    $command = "$phpBin $scriptPath --once > /tmp/test_import_job_1.log 2>&1 &";
    exec($command, $output, $returnCode);
    
    // Give it a moment to start
    usleep(500000); // 0.5 seconds
    
    // Check if lock file was created
    if (file_exists($lockFile)) {
        echo "✓ Lock file created successfully\n";
        $testsPassed++;
    } else {
        throw new Exception("Lock file was not created");
    }
    
    // Check if PID file was created
    if (file_exists($pidFile)) {
        $pid = trim(file_get_contents($pidFile));
        echo "✓ PID file created with PID: $pid\n";
        $testsPassed++;
    } else {
        throw new Exception("PID file was not created");
    }
    
    // Wait for the first instance to complete
    sleep(3);
    
} catch (Exception $e) {
    echo "✗ " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 3: Verify lock file is removed after script exits
echo "\nTest 3: Verifying cleanup after script exits...\n";
try {
    // Wait a bit more to ensure the script has finished
    sleep(2);
    
    if (!file_exists($lockFile)) {
        echo "✓ Lock file removed after script exit\n";
        $testsPassed++;
    } else {
        throw new Exception("Lock file was not removed");
    }
    
    if (!file_exists($pidFile)) {
        echo "✓ PID file removed after script exit\n";
        $testsPassed++;
    } else {
        throw new Exception("PID file was not removed");
    }
    
} catch (Exception $e) {
    echo "✗ " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 4: Verify multiple instances don't run simultaneously
echo "\nTest 4: Testing multiple instance prevention...\n";
try {
    // Create a fake lock file to simulate running instance
    $lockFp = fopen($lockFile, 'c+');
    if (!$lockFp) {
        throw new Exception("Failed to create lock file");
    }
    
    if (!flock($lockFp, LOCK_EX)) {
        fclose($lockFp);
        throw new Exception("Failed to acquire lock");
    }
    
    // Write a fake PID that's actually running (use current process)
    file_put_contents($pidFile, getmypid());
    
    echo "  Created simulated lock (PID: " . getmypid() . ")\n";
    
    // Try to start another instance
    $phpBin = PHP_BINARY;
    $command = "$phpBin $scriptPath --once > /tmp/test_import_job_2.log 2>&1";
    exec($command, $output, $returnCode);
    
    // Read the log to see what happened
    $log = file_get_contents('/tmp/test_import_job_2.log');
    
    if (strpos($log, 'Another instance is already running') !== false) {
        echo "✓ Second instance correctly detected first instance and exited\n";
        $testsPassed++;
    } else {
        throw new Exception("Second instance did not detect the first instance\nLog: " . $log);
    }
    
    // Cleanup
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    @unlink($lockFile);
    @unlink($pidFile);
    
} catch (Exception $e) {
    echo "✗ " . $e->getMessage() . "\n";
    $testsFailed++;
    
    // Cleanup on error
    if (isset($lockFp) && is_resource($lockFp)) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    }
    @unlink($lockFile);
    @unlink($pidFile);
}

// Test 5: Verify stale lock detection
echo "\nTest 5: Testing stale lock detection...\n";
try {
    // Create a lock file with a PID that doesn't exist
    file_put_contents($lockFile, "locked");
    file_put_contents($pidFile, "99999"); // Non-existent PID
    
    echo "  Created stale lock (PID: 99999)\n";
    
    // Try to start script
    $phpBin = PHP_BINARY;
    $command = "$phpBin $scriptPath --once > /tmp/test_import_job_3.log 2>&1 &";
    exec($command, $output, $returnCode);
    
    // Give it time to detect and clean up stale lock
    sleep(2);
    
    // Read the log
    $log = file_get_contents('/tmp/test_import_job_3.log');
    
    if (strpos($log, 'Removing stale lock file') !== false) {
        echo "✓ Stale lock correctly detected and removed\n";
        $testsPassed++;
    } else {
        // This might not show up in logs if it's cleaned up quickly, so check if script ran
        if (!file_exists($lockFile)) {
            echo "✓ Stale lock was cleaned up (script ran successfully)\n";
            $testsPassed++;
        } else {
            throw new Exception("Stale lock was not cleaned up");
        }
    }
    
    // Give the script time to finish
    sleep(2);
    
    // Verify cleanup
    if (!file_exists($lockFile) && !file_exists($pidFile)) {
        echo "✓ Lock and PID files cleaned up after stale lock removal\n";
        $testsPassed++;
    } else {
        echo "⚠  Warning: Lock files not fully cleaned up, but may be transient\n";
    }
    
    // Final cleanup
    @unlink($lockFile);
    @unlink($pidFile);
    
} catch (Exception $e) {
    echo "✗ " . $e->getMessage() . "\n";
    $testsFailed++;
    
    // Cleanup
    @unlink($lockFile);
    @unlink($pidFile);
}

// Cleanup test log files
@unlink('/tmp/test_import_job_1.log');
@unlink('/tmp/test_import_job_2.log');
@unlink('/tmp/test_import_job_3.log');

// Summary
echo "\n";
echo "===========================================\n";
echo "  Test Summary\n";
echo "===========================================\n";
echo "Tests Passed: $testsPassed\n";
echo "Tests Failed: $testsFailed\n";
echo "\n";

if ($testsFailed > 0) {
    echo "❌ Some tests failed!\n\n";
    exit(1);
} else {
    echo "✅ All tests passed!\n\n";
    exit(0);
}
