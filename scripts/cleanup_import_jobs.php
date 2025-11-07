#!/usr/bin/env php
<?php
/**
 * eclectyc-energy/scripts/cleanup_import_jobs.php
 * Clean up old import jobs with configurable retention policies
 * Last updated: 2025-11-07
 */

use App\Config\Database;
use App\Domain\Ingestion\ImportJobService;

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script can only be run from command line.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/London');

// Parse command line arguments
$args = getopt('d:v', ['days:', 'verbose', 'dry-run', 'help']);

if (isset($args['help'])) {
    echo "\n";
    echo "Eclectyc Energy Import Jobs Cleanup\n";
    echo "====================================\n\n";
    echo "Usage: php cleanup_import_jobs.php [options]\n\n";
    echo "Options:\n";
    echo "  -d, --days <n>     Keep jobs from last N days (default: 30)\n";
    echo "  -v, --verbose      Verbose output\n";
    echo "  --dry-run          Show what would be deleted without deleting\n";
    echo "  --help             Show this help message\n\n";
    echo "Retention Policy:\n";
    echo "  - Completed jobs: deleted after N days\n";
    echo "  - Failed jobs: deleted after N days\n";
    echo "  - Cancelled jobs: deleted after N days\n";
    echo "  - Queued/Processing jobs: never deleted automatically\n\n";
    echo "Examples:\n";
    echo "  php cleanup_import_jobs.php --days 30 --verbose\n";
    echo "  php cleanup_import_jobs.php --days 7 --dry-run\n\n";
    exit(0);
}

$daysToKeep = (int) ($args['d'] ?? $args['days'] ?? 30);
$verbose = isset($args['v']) || isset($args['verbose']);
$dryRun = isset($args['dry-run']);

echo "\n";
echo "===========================================\n";
echo "  Import Jobs Cleanup\n";
echo "  " . date('d/m/Y H:i:s') . "\n";
echo "===========================================\n";
echo "Retention: $daysToKeep days\n";
echo "Mode: " . ($dryRun ? "Dry run (no deletions)" : "Live") . "\n";
echo "\n";

try {
    $db = Database::getConnection();
    if (!$db) {
        throw new Exception('Failed to connect to database');
    }

    $jobService = new ImportJobService($db);

    // Get stats before cleanup
    if ($verbose) {
        $stmt = $db->query('
            SELECT 
                status,
                COUNT(*) as count,
                MIN(completed_at) as oldest,
                MAX(completed_at) as newest
            FROM import_jobs
            WHERE status IN ("completed", "failed", "cancelled")
              AND completed_at < DATE_SUB(NOW(), INTERVAL ' . $daysToKeep . ' DAY)
            GROUP BY status
        ');
        
        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($stats)) {
            echo "Jobs to be deleted:\n";
            $total = 0;
            foreach ($stats as $stat) {
                echo "  " . ucfirst($stat['status']) . ": " . $stat['count'];
                echo " (oldest: {$stat['oldest']}, newest: {$stat['newest']})\n";
                $total += $stat['count'];
            }
            echo "  Total: $total\n\n";
        } else {
            echo "No jobs to delete.\n\n";
        }
    }

    // Perform cleanup
    if (!$dryRun) {
        $deleted = $jobService->cleanupOldJobs($daysToKeep);
        
        echo "Deleted $deleted job(s).\n";
        
        // Clean up orphaned files
        $storageDir = dirname(__DIR__) . '/storage/imports';
        if (is_dir($storageDir)) {
            $files = glob($storageDir . '/*.csv');
            $filesDeleted = 0;
            
            foreach ($files as $file) {
                $fileAge = time() - filemtime($file);
                $daysOld = floor($fileAge / 86400);
                
                if ($daysOld > $daysToKeep) {
                    if (unlink($file)) {
                        $filesDeleted++;
                        if ($verbose) {
                            echo "  Deleted orphaned file: " . basename($file) . " ($daysOld days old)\n";
                        }
                    }
                }
            }
            
            if ($filesDeleted > 0) {
                echo "Deleted $filesDeleted orphaned file(s).\n";
            }
        }
    } else {
        echo "Dry run complete. No changes made.\n";
    }

    // Show final stats
    if ($verbose && !$dryRun) {
        echo "\n";
        $stmt = $db->query('
            SELECT 
                status,
                COUNT(*) as count,
                SUM(CASE WHEN completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as last_7_days,
                SUM(CASE WHEN completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as last_30_days
            FROM import_jobs
            WHERE status IN ("completed", "failed", "cancelled")
            GROUP BY status
        ');
        
        $finalStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($finalStats)) {
            echo "Remaining jobs:\n";
            foreach ($finalStats as $stat) {
                echo "  " . ucfirst($stat['status']) . ": " . $stat['count'];
                echo " (7d: {$stat['last_7_days']}, 30d: {$stat['last_30_days']})\n";
            }
        }
    }

    echo "\nCleanup complete.\n\n";
    exit(0);

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    exit(1);
}
