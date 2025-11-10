<?php
/**
 * eclectyc-energy/scripts/check_import_setup.php
 * Verify import system configuration and identify issues
 * Created: 2025-11-10
 */

use App\Config\Database;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/London');

echo "\n";
echo "===========================================\n";
echo "  Eclectyc Energy Import System Check\n";
echo "  " . date('d/m/Y H:i:s') . "\n";
echo "===========================================\n\n";

$issues = [];
$warnings = [];
$success = [];

// 1. Check database connection
echo "1. Checking database connection...\n";
try {
    $db = Database::getConnection();
    if (!$db) {
        $issues[] = "Database connection failed - check .env configuration";
    } else {
        $success[] = "✓ Database connection successful";
    }
} catch (Exception $e) {
    $issues[] = "Database error: " . $e->getMessage();
    $db = null;
}

// 2. Check import_jobs table exists
if ($db) {
    echo "2. Checking import_jobs table...\n";
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'import_jobs'");
        if ($stmt->rowCount() === 0) {
            $issues[] = "import_jobs table does not exist - run migrations";
        } else {
            $success[] = "✓ import_jobs table exists";
            
            // Check for queued jobs
            $stmt = $db->query("SELECT COUNT(*) as count FROM import_jobs WHERE status = 'queued'");
            $queuedCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($queuedCount > 0) {
                $warnings[] = "! $queuedCount job(s) stuck in QUEUED status";
                
                // Show details of queued jobs
                $stmt = $db->query("
                    SELECT batch_id, filename, import_type, queued_at, 
                           TIMESTAMPDIFF(MINUTE, queued_at, NOW()) as minutes_queued
                    FROM import_jobs 
                    WHERE status = 'queued'
                    ORDER BY queued_at ASC
                    LIMIT 5
                ");
                $queuedJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo "\n   Queued Jobs:\n";
                foreach ($queuedJobs as $job) {
                    echo "   - " . substr($job['batch_id'], 0, 8) . "... | ";
                    echo $job['filename'] . " | ";
                    echo "Type: " . $job['import_type'] . " | ";
                    echo "Queued: " . $job['minutes_queued'] . " min ago\n";
                }
                echo "\n";
            } else {
                $success[] = "✓ No jobs stuck in queue";
            }
        }
    } catch (PDOException $e) {
        $issues[] = "Error checking import_jobs table: " . $e->getMessage();
    }
}

// 3. Check storage/imports directory
echo "3. Checking storage/imports directory...\n";
$storageDir = dirname(__DIR__) . '/storage/imports';
if (!is_dir($storageDir)) {
    $warnings[] = "! storage/imports directory does not exist (will be created on first upload)";
} else {
    if (!is_writable($storageDir)) {
        $issues[] = "storage/imports directory is not writable - check permissions";
    } else {
        $success[] = "✓ storage/imports directory exists and is writable";
    }
    
    // Check for orphaned files
    $files = glob($storageDir . '/*');
    if ($files && count($files) > 0) {
        $warnings[] = "! " . count($files) . " file(s) in storage/imports directory";
    }
}

// 4. Check logs directory
echo "4. Checking logs directory...\n";
$logsDir = dirname(__DIR__) . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
    $success[] = "✓ Created logs directory";
} else {
    if (!is_writable($logsDir)) {
        $issues[] = "logs directory is not writable - check permissions";
    } else {
        $success[] = "✓ logs directory exists and is writable";
    }
}

// 5. Check if process_import_jobs.php is running
echo "5. Checking if import worker is running...\n";
$workerRunning = false;

// Try to find the process on Unix-like systems
if (PHP_OS_FAMILY !== 'Windows') {
    $output = shell_exec('ps aux | grep process_import_jobs.php | grep -v grep');
    if ($output) {
        $workerRunning = true;
        $success[] = "✓ Import worker process is running";
    }
}

if (!$workerRunning) {
    $issues[] = "Import worker is NOT running - jobs will stay in QUEUED status";
    echo "\n   To fix this issue, you need to start the import worker:\n\n";
    echo "   Option 1: Run manually (for testing):\n";
    echo "   php scripts/process_import_jobs.php\n\n";
    echo "   Option 2: Run as cron job (recommended):\n";
    echo "   Add to crontab: * * * * * cd " . dirname(__DIR__) . " && php scripts/process_import_jobs.php --once >> logs/import_worker_cron.log 2>&1\n\n";
    echo "   Option 3: Run as systemd service:\n";
    echo "   See deployment/systemd-import-worker.service\n\n";
}

// 6. Check environment variables
echo "6. Checking environment configuration...\n";
$requiredEnvVars = ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
foreach ($requiredEnvVars as $var) {
    if (empty($_ENV[$var])) {
        $issues[] = "Missing environment variable: $var";
    }
}
if (empty($issues)) {
    $success[] = "✓ Required environment variables are set";
}

// Print summary
echo "\n";
echo "===========================================\n";
echo "  SUMMARY\n";
echo "===========================================\n\n";

if (!empty($success)) {
    echo "✓ SUCCESS (" . count($success) . "):\n";
    foreach ($success as $msg) {
        echo "  $msg\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "! WARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $msg) {
        echo "  $msg\n";
    }
    echo "\n";
}

if (!empty($issues)) {
    echo "✗ ISSUES (" . count($issues) . "):\n";
    foreach ($issues as $msg) {
        echo "  $msg\n";
    }
    echo "\n";
}

if (empty($issues)) {
    if (!empty($warnings)) {
        echo "Status: Import system is configured but has warnings.\n";
        exit(0);
    } else {
        echo "Status: Import system is fully configured and ready!\n";
        exit(0);
    }
} else {
    echo "Status: Import system has configuration issues that need to be fixed.\n";
    exit(1);
}
