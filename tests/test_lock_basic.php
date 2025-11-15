#!/usr/bin/env php
<?php
/**
 * Simple test to verify the lock mechanism without database
 */

echo "\n";
echo "Testing Lock Mechanism\n";
echo "======================\n\n";

$lockFile = dirname(__DIR__) . '/storage/process_import_jobs.lock';
$pidFile = dirname(__DIR__) . '/storage/process_import_jobs.pid';

// Cleanup any existing files
@unlink($lockFile);
@unlink($pidFile);

echo "1. Testing that lock file can be created...\n";
$storageDir = dirname($lockFile);
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

$lockFp = fopen($lockFile, 'c+');
if (!$lockFp) {
    echo "✗ Failed to create lock file\n";
    exit(1);
}

if (flock($lockFp, LOCK_EX | LOCK_NB)) {
    echo "✓ Successfully acquired exclusive lock\n";
    file_put_contents($pidFile, getmypid());
    echo "✓ PID file created\n";
} else {
    echo "✗ Failed to acquire lock\n";
    fclose($lockFp);
    exit(1);
}

echo "\n2. Testing that second instance cannot acquire lock...\n";
$lockFp2 = fopen($lockFile, 'c+');
if (!flock($lockFp2, LOCK_EX | LOCK_NB)) {
    echo "✓ Second instance correctly blocked from acquiring lock\n";
    fclose($lockFp2);
} else {
    echo "✗ Second instance acquired lock (should not happen!)\n";
    flock($lockFp2, LOCK_UN);
    fclose($lockFp2);
    exit(1);
}

echo "\n3. Testing cleanup...\n";
flock($lockFp, LOCK_UN);
fclose($lockFp);
unlink($lockFile);
unlink($pidFile);
echo "✓ Lock and PID files removed\n";

if (!file_exists($lockFile) && !file_exists($pidFile)) {
    echo "✓ Cleanup verified\n";
} else {
    echo "✗ Files still exist after cleanup\n";
    exit(1);
}

echo "\n✅ All basic lock mechanism tests passed!\n\n";
exit(0);
