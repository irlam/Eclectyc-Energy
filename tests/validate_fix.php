#!/usr/bin/env php
<?php
/**
 * Final validation script - runs all tests and verifies the fix
 */

echo "\n";
echo "===============================================\n";
echo "  FINAL VALIDATION - Connection Exhaustion Fix\n";
echo "  " . date('d/m/Y H:i:s') . "\n";
echo "===============================================\n\n";

$testsDir = __DIR__;
$failed = false;

// Test 1: Basic lock mechanism
echo "Test 1: Basic Lock Mechanism\n";
echo "-----------------------------\n";
$output = [];
$returnCode = 0;
exec("php $testsDir/test_lock_basic.php", $output, $returnCode);
if ($returnCode === 0) {
    echo "✅ PASSED\n\n";
} else {
    echo "❌ FAILED\n";
    echo implode("\n", $output) . "\n\n";
    $failed = true;
}

// Test 2: Cron scenario simulation
echo "Test 2: Cron Scenario Simulation\n";
echo "---------------------------------\n";
$output = [];
$returnCode = 0;
exec("php $testsDir/test_cron_scenario.php", $output, $returnCode);
if ($returnCode === 0) {
    echo "✅ PASSED\n\n";
} else {
    echo "❌ FAILED\n";
    echo implode("\n", $output) . "\n\n";
    $failed = true;
}

// Test 3: Verify script syntax
echo "Test 3: Script Syntax Check\n";
echo "----------------------------\n";
$scriptPath = dirname($testsDir) . '/scripts/process_import_jobs.php';
$output = [];
$returnCode = 0;
exec("php -l $scriptPath 2>&1", $output, $returnCode);
if ($returnCode === 0) {
    echo "✅ PASSED - No syntax errors\n\n";
} else {
    echo "❌ FAILED\n";
    echo implode("\n", $output) . "\n\n";
    $failed = true;
}

// Test 4: Verify help text
echo "Test 4: Help Text Verification\n";
echo "-------------------------------\n";
$output = [];
$returnCode = 0;
exec("php $scriptPath --help 2>&1", $output, $returnCode);
$helpText = implode("\n", $output);
if (strpos($helpText, 'lock file') !== false && strpos($helpText, 'multiple instances') !== false) {
    echo "✅ PASSED - Help text includes lock mechanism info\n\n";
} else {
    echo "❌ FAILED - Help text missing lock mechanism info\n\n";
    $failed = true;
}

// Test 5: Verify storage directory is created
echo "Test 5: Storage Directory Creation\n";
echo "-----------------------------------\n";
$storageDir = dirname($testsDir) . '/storage';
if (is_dir($storageDir) && is_writable($storageDir)) {
    echo "✅ PASSED - Storage directory exists and is writable\n\n";
} else {
    echo "⚠️  WARNING - Storage directory may not exist (will be created on first run)\n\n";
}

// Test 6: Verify no lock files remain
echo "Test 6: Lock File Cleanup\n";
echo "--------------------------\n";
$lockFile = $storageDir . '/process_import_jobs.lock';
$pidFile = $storageDir . '/process_import_jobs.pid';
if (!file_exists($lockFile) && !file_exists($pidFile)) {
    echo "✅ PASSED - No stale lock files\n\n";
} else {
    echo "⚠️  WARNING - Lock files exist (cleaning up...)\n";
    @unlink($lockFile);
    @unlink($pidFile);
    echo "Cleaned up\n\n";
}

// Summary
echo "===============================================\n";
echo "  VALIDATION SUMMARY\n";
echo "===============================================\n\n";

if (!$failed) {
    echo "✅ ALL TESTS PASSED!\n\n";
    echo "The fix is ready for deployment.\n\n";
    echo "Deployment Steps:\n";
    echo "-----------------\n";
    echo "1. Update process_import_jobs.php on production server\n";
    echo "2. Update cron configuration to use --once flag:\n";
    echo "   */2 * * * * cd /path && php scripts/process_import_jobs.php --once >> logs/import_worker.log 2>&1\n";
    echo "3. Kill any running instances: pkill -f process_import_jobs.php\n";
    echo "4. Monitor logs and connections for first hour\n\n";
    echo "Documentation:\n";
    echo "--------------\n";
    echo "- Full setup guide: docs/CRON_SETUP_FIX.md\n";
    echo "- Fix summary: FIX_SUMMARY_MULTIPLE_INSTANCES.md\n";
    echo "- Connection troubleshooting: docs/DB_CONNECTION_FIX.md\n\n";
    exit(0);
} else {
    echo "❌ SOME TESTS FAILED!\n\n";
    echo "Please review the failed tests above before deploying.\n\n";
    exit(1);
}
