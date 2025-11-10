#!/usr/bin/env php
<?php
/**
 * eclectyc-energy/scripts/process_scheduled_reports.php
 * Process scheduled reports that are due to run
 * Should be run regularly via cron job (e.g., every hour)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Database;
use App\Models\ScheduledReport;
use App\Domain\Reports\ReportGenerationService;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Parse command line options
$options = getopt('v', ['verbose', 'force:', 'limit:']);
$verbose = isset($options['v']) || isset($options['verbose']);
$forceId = $options['force'] ?? null;
$limit = isset($options['limit']) ? (int)$options['limit'] : 10;

if ($verbose) {
    echo "Processing scheduled reports...\n";
    echo "---\n";
}

try {
    // Get database connection
    $pdo = Database::getConnection();
    ScheduledReport::setPdo($pdo);

    // Initialize service
    $reportService = new ReportGenerationService($pdo);

    // Get reports due to run
    $reports = [];
    
    if ($forceId) {
        // Force run a specific report
        $report = ScheduledReport::find((int)$forceId);
        if ($report) {
            $reports[] = $report;
        }
        if ($verbose) {
            echo "Force running report ID: {$forceId}\n\n";
        }
    } else {
        // Get reports that are due
        $stmt = $pdo->prepare('
            SELECT * FROM scheduled_reports
            WHERE is_active = 1
            AND frequency != "manual"
            AND (next_run_at IS NULL OR next_run_at <= NOW())
            ORDER BY next_run_at ASC
            LIMIT ?
        ');
        $stmt->execute([$limit]);
        $reportsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($reportsData as $data) {
            $reports[] = new ScheduledReport($data);
        }

        if ($verbose) {
            echo "Found " . count($reports) . " report(s) due to run\n\n";
        }
    }

    $successCount = 0;
    $failureCount = 0;

    foreach ($reports as $report) {
        if ($verbose) {
            echo "Processing report #{$report->id}: {$report->name}\n";
            echo "  Type: {$report->report_type}\n";
            echo "  Format: {$report->report_format}\n";
        }

        try {
            $result = $reportService->generateAndSend($report);

            if ($result['success']) {
                $successCount++;
                if ($verbose) {
                    echo "  ✓ Report generated and sent to {$result['emails_sent']} recipient(s)\n";
                    echo "  File: {$result['file_path']}\n";
                }
            } else {
                $failureCount++;
                if ($verbose) {
                    echo "  ✗ Failed: {$result['error']}\n";
                }
            }
        } catch (\Exception $e) {
            $failureCount++;
            if ($verbose) {
                echo "  ✗ Exception: " . $e->getMessage() . "\n";
            }
            error_log("Failed to process scheduled report #{$report->id}: " . $e->getMessage());
        }

        if ($verbose) {
            echo "\n";
        }
    }

    if ($verbose) {
        echo "---\n";
        echo "Summary:\n";
        echo "  Reports processed: " . count($reports) . "\n";
        echo "  Successful: {$successCount}\n";
        echo "  Failed: {$failureCount}\n";
    }

    // Log to audit_logs
    $stmt = $pdo->prepare('
        INSERT INTO audit_logs (event_type, event_data, created_by)
        VALUES (?, ?, ?)
    ');
    $stmt->execute([
        'scheduled_reports_processing',
        json_encode([
            'processed_count' => count($reports),
            'success_count' => $successCount,
            'failure_count' => $failureCount
        ]),
        'system'
    ]);

    exit($failureCount > 0 ? 1 : 0);

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Scheduled reports processing error: " . $e->getMessage());
    exit(1);
}
