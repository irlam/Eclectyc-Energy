<?php
/**
 * eclectyc-energy/scripts/aggregate_cron.php
 * CLI helper for running daily, weekly, monthly, or annual aggregations.
 * Last updated: 06/11/2025
 */

use App\Config\Database;
use App\Domain\Aggregation\DailyAggregationSummary;
use App\Domain\Aggregation\DailyAggregator;
use App\Domain\Aggregation\PeriodAggregationSummary;
use App\Domain\Aggregation\PeriodAggregator;

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script can only be run from command line.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenvClass = 'Dotenv\\Dotenv';
if (class_exists($dotenvClass)) {
    $dotenv = $dotenvClass::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
}

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/London');

/**
 * @param array<int, string> $ranges
 */
function runAggregations(array $ranges, DateTimeImmutable $targetDate, bool $verbose = false): int
{
    $ranges = aggregationNormaliseRanges($ranges);

    $pdo = Database::getConnection();
    if (!$pdo) {
        throw new RuntimeException('Failed to connect to database');
    }

    $exitCode = 0;
    $dailyAggregator = null;
    $periodAggregator = null;
    
    $overallStartTime = new DateTime();

    foreach ($ranges as $range) {
        $rangeStartTime = new DateTime();
        $rangeStatus = 'completed';
        $rangeExitCode = 0;
        $rangeLogData = [];
        
        try {
            switch ($range) {
                case 'daily':
                    $dailyAggregator ??= new DailyAggregator($pdo);
                    $dailySummary = $dailyAggregator->aggregate($targetDate);
                    $rangeLogData = $dailySummary->toArray();
                    aggregationLogAudit($pdo, 'daily', $rangeLogData);
                    if ($verbose) {
                        aggregationOutputDailySummary($dailySummary);
                    }
                    if ($dailySummary->getErrors() > 0) {
                        $exitCode = 1;
                        $rangeExitCode = 1;
                        $rangeStatus = 'failed';
                    }
                    break;

                case 'weekly':
                    $periodAggregator ??= new PeriodAggregator($pdo);
                    [$start, $end] = aggregationResolveWeeklyRange($targetDate);
                    $weeklySummary = $periodAggregator->aggregate('weekly', $start, $end);
                    $rangeLogData = $weeklySummary->toArray();
                    aggregationLogAudit($pdo, 'weekly', $rangeLogData);
                    if ($verbose) {
                        aggregationOutputPeriodSummary($weeklySummary);
                    }
                    if ($weeklySummary->getErrors() > 0) {
                        $exitCode = 1;
                        $rangeExitCode = 1;
                        $rangeStatus = 'failed';
                    }
                    break;

                case 'monthly':
                    $periodAggregator ??= $periodAggregator ?? new PeriodAggregator($pdo);
                    [$start, $end] = aggregationResolveMonthlyRange($targetDate);
                    $monthlySummary = $periodAggregator->aggregate('monthly', $start, $end);
                    $rangeLogData = $monthlySummary->toArray();
                    aggregationLogAudit($pdo, 'monthly', $rangeLogData);
                    if ($verbose) {
                        aggregationOutputPeriodSummary($monthlySummary);
                    }
                    if ($monthlySummary->getErrors() > 0) {
                        $exitCode = 1;
                        $rangeExitCode = 1;
                        $rangeStatus = 'failed';
                    }
                    break;

                case 'annual':
                    $periodAggregator ??= $periodAggregator ?? new PeriodAggregator($pdo);
                    [$start, $end] = aggregationResolveAnnualRange($targetDate);
                    $annualSummary = $periodAggregator->aggregate('annual', $start, $end);
                    $rangeLogData = $annualSummary->toArray();
                    aggregationLogAudit($pdo, 'annual', $rangeLogData);
                    if ($verbose) {
                        aggregationOutputPeriodSummary($annualSummary);
                    }
                    if ($annualSummary->getErrors() > 0) {
                        $exitCode = 1;
                        $rangeExitCode = 1;
                        $rangeStatus = 'failed';
                    }
                    break;
            }
        } catch (Throwable $exception) {
            $rangeStatus = 'failed';
            $rangeExitCode = 1;
            $rangeLogData['error_message'] = $exception->getMessage();
            if ($verbose) {
                fwrite(STDERR, sprintf("Error running %s aggregation: %s\n", $range, $exception->getMessage()));
            }
            $exitCode = 1;
        }
        
        // Log to cron_logs table
        $rangeEndTime = new DateTime();
        aggregationLogCronExecution(
            $pdo,
            "aggregate_{$range}",
            $range,
            $rangeStartTime,
            $rangeEndTime,
            $rangeStatus,
            $rangeExitCode,
            $rangeLogData
        );
    }

    return $exitCode;
}

function runAggregationsFromCli(): int
{
    $args = getopt('d:r:av', ['date:', 'range:', 'all', 'verbose']);
    $dateArgument = $args['d'] ?? $args['date'] ?? null;
    $rangeArgument = $args['r'] ?? $args['range'] ?? null;
    $verbose = isset($args['v']) || isset($args['verbose']);

    $ranges = [];
    if (isset($args['a']) || isset($args['all'])) {
        $ranges[] = 'all';
    }
    if ($rangeArgument !== null) {
        $ranges[] = $rangeArgument;
    }
    if (empty($ranges)) {
        $ranges[] = 'daily';
    }

    $targetDate = aggregationParseTargetDate($dateArgument);

    return runAggregations($ranges, $targetDate, $verbose);
}

function aggregationParseTargetDate(?string $dateString): DateTimeImmutable
{
    if ($dateString === null) {
        return new DateTimeImmutable('yesterday');
    }

    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $dateString);
    if ($parsed === false) {
        throw new RuntimeException('Invalid date format. Use YYYY-MM-DD.');
    }

    return $parsed;
}

/**
 * @param array<int, string> $ranges
 * @return array<int, string>
 */
function aggregationNormaliseRanges(array $ranges): array
{
    if (empty($ranges)) {
        $ranges = ['daily'];
    }

    $expanded = [];
    foreach ($ranges as $range) {
        $range = strtolower($range);
        if ($range === 'all') {
            $expanded = array_merge($expanded, ['daily', 'weekly', 'monthly', 'annual']);
            continue;
        }
        $expanded[] = $range;
    }

    $expanded = array_values(array_unique($expanded));
    $allowed = ['daily', 'weekly', 'monthly', 'annual'];

    foreach ($expanded as $range) {
        if (!in_array($range, $allowed, true)) {
            throw new RuntimeException('Unsupported aggregation range: ' . $range);
        }
    }

    return $expanded;
}

function aggregationResolveWeeklyRange(DateTimeImmutable $targetDate): array
{
    $weekStart = $targetDate->modify('monday this week');
    if ($weekStart > $targetDate) {
        $weekStart = $weekStart->modify('-7 days');
    }
    $weekEnd = $weekStart->modify('+6 days');

    return [$weekStart, $weekEnd];
}

function aggregationResolveMonthlyRange(DateTimeImmutable $targetDate): array
{
    $monthStart = $targetDate->modify('first day of this month');
    $monthEnd = $monthStart->modify('last day of this month');

    return [$monthStart, $monthEnd];
}

function aggregationResolveAnnualRange(DateTimeImmutable $targetDate): array
{
    $yearStart = $targetDate->setDate((int) $targetDate->format('Y'), 1, 1);
    $yearEnd = $yearStart->setDate((int) $yearStart->format('Y'), 12, 31);

    return [$yearStart, $yearEnd];
}

function aggregationLogAudit(PDO $pdo, string $range, array $payload): void
{
    try {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        $json = json_encode(['error' => $exception->getMessage()]) ?: '{}';
    }

    try {
        $stmt = $pdo->prepare('INSERT INTO audit_logs (action, entity_type, new_values) VALUES (:action, :entity_type, :payload)');
        $stmt->execute([
            'action' => $range . '_aggregation',
            'entity_type' => 'system',
            'payload' => $json,
        ]);
    } catch (PDOException $exception) {
        fwrite(STDERR, 'Failed to log aggregation audit: ' . $exception->getMessage() . "\n");
    }
}

/**
 * Log to cron_logs table for better visibility
 */
function aggregationLogCronExecution(PDO $pdo, string $jobName, string $jobType, DateTime $startTime, ?DateTime $endTime, string $status, int $exitCode, array $logData = []): void
{
    try {
        // Check if table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'cron_logs'")->fetch();
        if (!$tableCheck) {
            return; // Table doesn't exist yet, skip logging
        }
        
        $durationSeconds = null;
        if ($endTime !== null) {
            $durationSeconds = $endTime->getTimestamp() - $startTime->getTimestamp();
        }
        
        $recordsProcessed = $logData['records_processed'] ?? $logData['total_meters'] ?? 0;
        $recordsFailed = $logData['records_failed'] ?? 0;
        $errorsCount = $logData['errors'] ?? $logData['error_count'] ?? 0;
        $warningsCount = $logData['warnings'] ?? $logData['warning_count'] ?? 0;
        $errorMessage = $logData['error_message'] ?? null;
        
        $logDataJson = json_encode($logData, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        
        $stmt = $pdo->prepare('
            INSERT INTO cron_logs 
            (job_name, job_type, start_time, end_time, duration_seconds, status, exit_code, 
             records_processed, records_failed, errors_count, warnings_count, error_message, log_data)
            VALUES 
            (:job_name, :job_type, :start_time, :end_time, :duration_seconds, :status, :exit_code,
             :records_processed, :records_failed, :errors_count, :warnings_count, :error_message, :log_data)
        ');
        
        $stmt->execute([
            'job_name' => $jobName,
            'job_type' => $jobType,
            'start_time' => $startTime->format('Y-m-d H:i:s'),
            'end_time' => $endTime ? $endTime->format('Y-m-d H:i:s') : null,
            'duration_seconds' => $durationSeconds,
            'status' => $status,
            'exit_code' => $exitCode,
            'records_processed' => $recordsProcessed,
            'records_failed' => $recordsFailed,
            'errors_count' => $errorsCount,
            'warnings_count' => $warningsCount,
            'error_message' => $errorMessage,
            'log_data' => $logDataJson,
        ]);
    } catch (Exception $exception) {
        fwrite(STDERR, 'Failed to log cron execution: ' . $exception->getMessage() . "\n");
    }
}

function aggregationOutputDailySummary(DailyAggregationSummary $summary): void
{
    echo "Daily aggregation for " . $summary->getDate()->format('Y-m-d') . "\n";
    echo "  Total meters: " . $summary->getTotalMeters() . "\n";
    echo "  With readings: " . $summary->getMetersWithData() . "\n";
    echo "  Without readings: " . $summary->getMetersWithoutData() . "\n";
    echo "  Errors: " . $summary->getErrors() . "\n";

    foreach ($summary->getErrorMessages() as $message) {
        echo "    - $message\n";
    }

    echo "\n";
}

function aggregationOutputPeriodSummary(PeriodAggregationSummary $summary): void
{
    echo ucfirst($summary->getPeriod()) . " aggregation for " . $summary->getStartDate()->format('Y-m-d') . ' to ' . $summary->getEndDate()->format('Y-m-d') . "\n";
    echo "  Total meters: " . $summary->getTotalMeters() . "\n";
    echo "  With data: " . $summary->getMetersWithData() . "\n";
    echo "  Without data: " . $summary->getMetersWithoutData() . "\n";
    echo "  Errors: " . $summary->getErrors() . "\n";

    foreach ($summary->getErrorMessages() as $message) {
        echo "    - $message\n";
    }

    echo "\n";
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $exitCode = runAggregationsFromCli();
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n");
        $exitCode = 1;
    }

    exit($exitCode);
}