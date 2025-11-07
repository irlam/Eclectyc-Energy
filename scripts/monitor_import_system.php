#!/usr/bin/env php
<?php
/**
 * eclectyc-energy/scripts/monitor_import_system.php
 * Monitor import system health and send alerts
 * Last updated: 2025-11-07
 */

use App\Config\Database;
use App\Domain\Ingestion\ImportJobService;
use App\Domain\Ingestion\ImportMonitoringService;
use App\Domain\Ingestion\ImportAlertService;

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script can only be run from command line.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/London');

// Parse command line arguments
$args = getopt('v', ['verbose', 'handle-stuck', 'send-alerts', 'help']);

if (isset($args['help'])) {
    echo "\n";
    echo "Eclectyc Energy Import System Monitor\n";
    echo "=====================================\n\n";
    echo "Usage: php monitor_import_system.php [options]\n\n";
    echo "Options:\n";
    echo "  -v, --verbose      Verbose output\n";
    echo "  --handle-stuck     Automatically mark stuck jobs as failed\n";
    echo "  --send-alerts      Send alerts for issues found\n";
    echo "  --help             Show this help message\n\n";
    echo "Examples:\n";
    echo "  php monitor_import_system.php --verbose\n";
    echo "  php monitor_import_system.php --handle-stuck --send-alerts\n\n";
    exit(0);
}

$verbose = isset($args['v']) || isset($args['verbose']);
$handleStuck = isset($args['handle-stuck']);
$sendAlerts = isset($args['send-alerts']);

echo "\n";
echo "===========================================\n";
echo "  Import System Health Monitor\n";
echo "  " . date('d/m/Y H:i:s') . "\n";
echo "===========================================\n\n";

try {
    $db = Database::getConnection();
    if (!$db) {
        throw new Exception('Failed to connect to database');
    }

    $monitoringService = new ImportMonitoringService($db);
    $jobService = new ImportJobService($db);
    $alertService = new ImportAlertService($db);

    // Get system health
    $health = $monitoringService->getSystemHealth();

    echo "System Status: " . strtoupper($health['status']) . "\n\n";

    // Display metrics
    if ($verbose) {
        echo "Performance Metrics (Last 24 hours):\n";
        echo "  Jobs Completed: " . ($health['metrics']['jobs_completed'] ?? 0) . "\n";
        echo "  Avg Duration: " . round($health['metrics']['avg_duration_seconds'] ?? 0, 1) . "s\n";
        echo "  Total Rows Imported: " . number_format($health['metrics']['total_rows_imported'] ?? 0) . "\n";
        echo "  Avg Rows/Job: " . number_format($health['metrics']['avg_rows_per_job'] ?? 0) . "\n";
        echo "  Queued: " . ($health['metrics']['queued_count'] ?? 0) . "\n";
        echo "  Processing: " . ($health['metrics']['processing_count'] ?? 0) . "\n";
        echo "\n";
    }

    // Display alerts
    if (!empty($health['alerts'])) {
        echo "Alerts:\n";
        foreach ($health['alerts'] as $alert) {
            $icon = $alert['severity'] === 'critical' ? '✗' : '⚠';
            echo "  $icon {$alert['message']}\n";
        }
        echo "\n";
    } else {
        echo "No alerts.\n\n";
    }

    // Handle stuck jobs if requested
    if ($handleStuck) {
        $stuckCount = $monitoringService->handleStuckJobs(60);
        if ($stuckCount > 0) {
            echo "Marked $stuckCount stuck job(s) as failed.\n\n";
        }
    }

    // Send alerts if requested
    if ($sendAlerts && !empty($health['alerts'])) {
        foreach ($health['alerts'] as $alert) {
            switch ($alert['type']) {
                case 'stuck_jobs':
                    $stuckJobs = $monitoringService->getStuckJobs();
                    $alertService->sendStuckJobsAlert($stuckJobs);
                    echo "Sent stuck jobs alert.\n";
                    break;

                case 'high_failure_rate':
                case 'elevated_failure_rate':
                    $alertService->sendHighFailureRateAlert($alert['rate'], 24);
                    echo "Sent high failure rate alert.\n";
                    break;

                case 'queue_backlog':
                    $alertService->sendQueueBacklogAlert($alert['count']);
                    echo "Sent queue backlog alert.\n";
                    break;
            }
        }
        echo "\n";
    }

    // Get retry statistics
    if ($verbose) {
        $retryStats = $monitoringService->getRetryStatistics(24);
        if ($retryStats['total_with_retries'] > 0) {
            echo "Retry Statistics (Last 24 hours):\n";
            echo "  Jobs with Retries: " . $retryStats['total_with_retries'] . "\n";
            echo "  Avg Retries: " . round($retryStats['avg_retries'], 1) . "\n";
            echo "  Eventually Succeeded: " . $retryStats['eventually_succeeded'] . "\n";
            echo "  Permanently Failed: " . $retryStats['permanently_failed'] . "\n";
            echo "\n";
        }
    }

    // Send alerts for failed jobs that need attention
    if ($sendAlerts) {
        $failedJobs = $jobService->getFailedJobsNeedingAlerts(10);
        if (!empty($failedJobs)) {
            $alertService->sendBatchFailureAlert($failedJobs);
            
            foreach ($failedJobs as $job) {
                $jobService->markAlertSent($job['batch_id']);
            }
            
            echo "Sent alerts for " . count($failedJobs) . " failed job(s).\n\n";
        }
    }

    echo "Monitoring complete.\n\n";
    exit($health['status'] === 'critical' ? 2 : ($health['status'] === 'degraded' ? 1 : 0));

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    exit(3);
}
