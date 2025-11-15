<?php
/**
 * eclectyc-energy/scripts/process_import_jobs.php
 * Background worker for processing queued import jobs
 * Last updated: 2025-11-07
 */

use App\Config\Database;
use App\Domain\Ingestion\CsvIngestionService;
use App\Domain\Ingestion\ImportJobService;
use App\Domain\Ingestion\ImportAlertService;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/London');

// Lock file to prevent multiple instances
$lockFile = dirname(__DIR__) . '/storage/process_import_jobs.lock';
$pidFile = dirname(__DIR__) . '/storage/process_import_jobs.pid';

// Ensure storage directory exists
$storageDir = dirname($lockFile);
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0755, true);
}

// Parse command line arguments
$args = getopt('h', ['help', 'once', 'limit:']);

if (isset($args['h']) || isset($args['help'])) {
    echo "\n";
    echo "Eclectyc Energy Import Job Processor\n";
    echo "====================================\n\n";
    echo "Usage: php process_import_jobs.php [--once] [--limit=N]\n\n";
    echo "Options:\n";
    echo "  --once       Process queued jobs once and exit (default: continuous)\n";
    echo "  --limit=N    Maximum number of jobs to process per iteration (default: 10)\n";
    echo "  -h, --help   Show this help message\n\n";
    echo "Examples:\n";
    echo "  php process_import_jobs.php --once\n";
    echo "  php process_import_jobs.php --limit=5\n\n";
    echo "Note:\n";
    echo "  This script uses a lock file to prevent multiple instances from running\n";
    echo "  simultaneously. If the script is already running, it will exit immediately.\n\n";
    exit(0);
}

$runOnce = isset($args['once']);
$limit = isset($args['limit']) ? (int) $args['limit'] : 10;

// Try to acquire lock
$lockFp = fopen($lockFile, 'c+');
if (!$lockFp) {
    echo "ERROR: Failed to create lock file: $lockFile\n";
    exit(1);
}

// Try to get exclusive lock (non-blocking)
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    // Another instance is already running
    if (file_exists($pidFile)) {
        $runningPid = trim(file_get_contents($pidFile));
        // Check if the process is actually running
        if (posix_kill((int)$runningPid, 0)) {
            echo "Another instance is already running (PID: $runningPid).\n";
            echo "If you believe this is an error, remove the lock file: $lockFile\n";
            fclose($lockFp);
            exit(0);
        } else {
            // Stale lock - the process is not running anymore
            echo "Removing stale lock file from dead process (PID: $runningPid)\n";
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
            @unlink($lockFile);
            @unlink($pidFile);
            // Try again
            $lockFp = fopen($lockFile, 'c+');
            if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
                echo "ERROR: Failed to acquire lock after removing stale lock\n";
                fclose($lockFp);
                exit(1);
            }
        }
    } else {
        echo "Another instance is already running.\n";
        fclose($lockFp);
        exit(0);
    }
}

// Write PID file
file_put_contents($pidFile, getmypid());

// Register cleanup function to remove lock and PID file
$cleanup = function() use ($lockFp, $lockFile, $pidFile) {
    Database::closeConnection();
    if (is_resource($lockFp)) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    }
    @unlink($lockFile);
    @unlink($pidFile);
};

register_shutdown_function($cleanup);

// Handle termination signals gracefully
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() use ($cleanup) {
        echo "\nReceived SIGTERM, shutting down gracefully...\n";
        $cleanup();
        exit(0);
    });
    pcntl_signal(SIGINT, function() use ($cleanup) {
        echo "\nReceived SIGINT, shutting down gracefully...\n";
        $cleanup();
        exit(0);
    });
}

echo "\n";
echo "===========================================\n";
echo "  Eclectyc Energy Import Job Processor\n";
echo "  " . date('d/m/Y H:i:s') . "\n";
echo "===========================================\n";
echo "Mode: " . ($runOnce ? "Single run" : "Continuous") . "\n";
echo "Batch limit: $limit\n";
echo "\n";

try {
    $db = Database::getConnection();
    if (!$db) {
        throw new Exception('Failed to connect to database');
    }

    $jobService = new ImportJobService($db);
    $csvService = new CsvIngestionService($db);
    $alertService = new ImportAlertService($db);

    do {
        // Check for signals in continuous mode
        if (function_exists('pcntl_signal_dispatch')) {
            pcntl_signal_dispatch();
        }
        
        $jobs = $jobService->getQueuedJobs($limit);
        
        if (empty($jobs)) {
            if ($runOnce) {
                echo "No jobs in queue. Exiting.\n";
                break;
            }
            
            // Close connection before sleeping to free up resources
            Database::closeConnection();
            
            // Wait before checking again
            echo "[" . date('H:i:s') . "] No jobs in queue. Waiting 30 seconds...\n";
            sleep(30);
            
            // Reconnect after sleep
            $db = Database::getConnection();
            if (!$db) {
                throw new Exception('Failed to reconnect to database');
            }
            $jobService = new ImportJobService($db);
            $csvService = new CsvIngestionService($db);
            $alertService = new ImportAlertService($db);
            
            continue;
        }

        echo "[" . date('H:i:s') . "] Found " . count($jobs) . " job(s) to process.\n\n";

        foreach ($jobs as $job) {
            $batchId = $job['batch_id'];
            $filePath = $job['file_path'];
            $importType = $job['import_type'];
            $dryRun = (bool) $job['dry_run'];
            $userId = $job['user_id'];
            $retryCount = (int) ($job['retry_count'] ?? 0);
            $maxRetries = (int) ($job['max_retries'] ?? 3);

            echo "Processing job: $batchId\n";
            echo "  File: " . $job['filename'] . "\n";
            echo "  Type: $importType\n";
            echo "  Dry run: " . ($dryRun ? 'Yes' : 'No') . "\n";
            echo "  Retry: $retryCount/$maxRetries\n";

            // Check if file exists
            if (!file_exists($filePath)) {
                $jobService->updateStatus($batchId, 'failed', 'File not found: ' . $filePath);
                echo "  Status: FAILED (file not found)\n\n";
                continue;
            }

            try {
                // Update status to processing
                $jobService->updateStatus($batchId, 'processing');

                // Progress callback
                $progressCallback = function (int $processed, int $imported, int $warnings) use ($jobService, $batchId) {
                    $failed = $processed - $imported;
                    $jobService->updateProgress($batchId, $processed, $imported, $failed);
                };

                // Process the import
                $result = $csvService->ingestFromCsv(
                    $filePath,
                    $importType,
                    $batchId,
                    $dryRun,
                    $userId,
                    $progressCallback
                );

                // Update job with results
                $summary = $result->toArray();
                $status = $result->hasErrors() ? 'completed' : 'completed'; // Still completed even with errors
                $jobService->completeJob($batchId, $summary, $status);

                echo "  Status: COMPLETED\n";
                echo "    Processed: " . $result->getRecordsProcessed() . "\n";
                echo "    Imported: " . $result->getRecordsImported() . "\n";
                echo "    Failed: " . $result->getRecordsFailed() . "\n";

                // Clean up uploaded file if not dry run
                if (!$dryRun && file_exists($filePath)) {
                    @unlink($filePath);
                }

            } catch (\Throwable $e) {
                echo "  Status: FAILED\n";
                echo "  Error: " . $e->getMessage() . "\n";
                
                // Check if we should retry
                if ($jobService->canRetry($batchId)) {
                    // Calculate exponential backoff: 2^retryCount minutes
                    $delaySeconds = min(pow(2, $retryCount) * 60, 3600); // Cap at 1 hour
                    $jobService->retryJob($batchId, $delaySeconds);
                    
                    echo "  Will retry in " . round($delaySeconds / 60, 1) . " minutes\n";
                } else {
                    // Mark as permanently failed
                    $jobService->updateStatus($batchId, 'failed', $e->getMessage());
                    
                    // Send alert if not already sent
                    if (!$job['alert_sent']) {
                        $failedJob = $jobService->getJob($batchId);
                        $alertService->sendFailureAlert($failedJob);
                        $jobService->markAlertSent($batchId);
                        echo "  Alert sent to administrators\n";
                    }
                }
            }

            echo "\n";
        }

        if ($runOnce) {
            break;
        }

        // Close and reopen connection between iterations to prevent connection leaks
        Database::closeConnection();
        
        // Small delay between iterations
        sleep(5);
        
        // Reconnect for next iteration
        $db = Database::getConnection();
        if (!$db) {
            throw new Exception('Failed to reconnect to database');
        }
        $jobService = new ImportJobService($db);
        $csvService = new CsvIngestionService($db);
        $alertService = new ImportAlertService($db);

    } while (true);

    // Clean up will be handled by shutdown function
    echo "Import job processor finished.\n\n";
    exit(0);

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    // Cleanup will be handled by shutdown function
    exit(1);
}
