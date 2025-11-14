<?php
/**
 * eclectyc-energy/scripts/aggregate_orchestrated.php
 * Enhanced aggregation script with orchestration, telemetry, and failure alerts.
 * Last updated: 07/11/2025
 */

use App\Config\Database;
use App\Domain\Aggregation\DailyAggregator;
use App\Domain\Aggregation\PeriodAggregator;
use App\Domain\Aggregation\AggregationRangeResolver;
use App\Domain\Orchestration\SchedulerOrchestrator;
use App\Domain\Orchestration\TelemetryService;
use App\Domain\Orchestration\AlertService;

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

function runOrchestratedAggregation(): int
{
    $args = getopt('d:r:av', ['date:', 'range:', 'all', 'verbose', 'help']);
    
    if (isset($args['help'])) {
        printHelp();
        return 0;
    }
    
    $dateArgument = $args['d'] ?? $args['date'] ?? null;
    $rangeArgument = $args['r'] ?? $args['range'] ?? null;
    $verbose = isset($args['v']) || isset($args['verbose']);
    
    $ranges = [];
    if (isset($args['a']) || isset($args['all'])) {
        $ranges = ['daily', 'weekly', 'monthly', 'annual'];
    } elseif ($rangeArgument !== null) {
        $ranges = [$rangeArgument];
    } else {
        $ranges = ['daily'];
    }
    
    $targetDate = parseTargetDate($dateArgument);
    
    $pdo = Database::getConnection();
    if (!$pdo) {
        throw new RuntimeException('Failed to connect to database');
    }
    
    // Initialize services
    $telemetry = new TelemetryService($pdo);
    $alertConfig = [
        'admin_email' => $_ENV['ADMIN_EMAIL'] ?? null,
        'mail_enabled' => !empty($_ENV['MAIL_HOST']),
    ];
    $alertService = new AlertService($pdo, $alertConfig);
    $orchestrator = new SchedulerOrchestrator($pdo, $telemetry, $alertService);
    
    $exitCode = 0;
    
    if ($verbose) {
        echo "Starting orchestrated aggregation...\n";
        echo "Date: " . $targetDate->format('Y-m-d') . "\n";
        echo "Ranges: " . implode(', ', $ranges) . "\n\n";
    }
    
    foreach ($ranges as $range) {
        if ($verbose) {
            echo "Processing {$range} aggregation...\n";
        }
        
        try {
            // Use the existing aggregation logic wrapped in orchestration
            $result = executeRangeWithOrchestration($pdo, $range, $targetDate, $telemetry, $alertService);
            
            if ($verbose) {
                printResult($result);
            }
            
            if (!$result['success']) {
                $exitCode = 1;
            }
        } catch (Throwable $e) {
            if ($verbose) {
                fwrite(STDERR, "Error: {$e->getMessage()}\n");
            }
            $exitCode = 1;
        }
    }
    
    if ($verbose) {
        echo "\nAggregation completed.\n";
    }
    
    return $exitCode;
}

function executeRangeWithOrchestration(PDO $pdo, string $range, DateTimeImmutable $targetDate, TelemetryService $telemetry, AlertService $alertService): array
{
    $executionId = uniqid('exec_', true);
    $startTime = microtime(true);
    
    $telemetry->recordStart($executionId, $range, $targetDate);
    
    try {
        $dailyAggregator = new DailyAggregator($pdo);
        $periodAggregator = new PeriodAggregator($pdo);
        
        $metersProcessed = 0;
        $errors = 0;
        $warnings = 0;
        
        switch ($range) {
            case 'daily':
                $summary = $dailyAggregator->aggregate($targetDate);
                $metersProcessed = $summary->getMetersWithData();
                $errors = $summary->getErrors();
                break;
                
            case 'weekly':
                [$start, $end] = AggregationRangeResolver::resolveWeeklyRange($targetDate);
                $summary = $periodAggregator->aggregate('weekly', $start, $end);
                $metersProcessed = $summary->getMetersWithData();
                $errors = $summary->getErrors();
                break;
                
            case 'monthly':
                [$start, $end] = AggregationRangeResolver::resolveMonthlyRange($targetDate);
                $summary = $periodAggregator->aggregate('monthly', $start, $end);
                $metersProcessed = $summary->getMetersWithData();
                $errors = $summary->getErrors();
                break;
                
            case 'annual':
                [$start, $end] = AggregationRangeResolver::resolveAnnualRange($targetDate);
                $summary = $periodAggregator->aggregate('annual', $start, $end);
                $metersProcessed = $summary->getMetersWithData();
                $errors = $summary->getErrors();
                break;
        }
        
        $duration = microtime(true) - $startTime;
        
        $result = [
            'meters_processed' => $metersProcessed,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
        
        $telemetry->recordSuccess($executionId, $duration, $result);
        
        if ($warnings > 0) {
            $alertService->sendWarning($range, $result);
        }
        
        return [
            'success' => true,
            'execution_id' => $executionId,
            'range' => $range,
            'duration' => $duration,
            'meters_processed' => $metersProcessed,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
        
    } catch (Throwable $e) {
        $duration = microtime(true) - $startTime;
        $telemetry->recordFailure($executionId, $duration, $e);
        $alertService->sendFailureAlert($range, $e);
        
        return [
            'success' => false,
            'execution_id' => $executionId,
            'range' => $range,
            'duration' => $duration,
            'error_message' => $e->getMessage(),
        ];
    }
}

function parseTargetDate(?string $dateString): DateTimeImmutable
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

function printResult(array $result): void
{
    echo "  Execution ID: {$result['execution_id']}\n";
    echo "  Duration: " . round($result['duration'], 2) . "s\n";
    echo "  Status: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    
    if ($result['success']) {
        echo "  Meters processed: {$result['meters_processed']}\n";
        echo "  Errors: {$result['errors']}\n";
        echo "  Warnings: {$result['warnings']}\n";
    } else {
        echo "  Error: {$result['error_message']}\n";
    }
    
    echo "\n";
}

function printHelp(): void
{
    echo "Orchestrated Aggregation Script\n";
    echo "===============================\n\n";
    echo "Usage: php aggregate_orchestrated.php [options]\n\n";
    echo "Options:\n";
    echo "  -d, --date <date>    Target date (YYYY-MM-DD, default: yesterday)\n";
    echo "  -r, --range <range>  Aggregation range (daily, weekly, monthly, annual)\n";
    echo "  -a, --all            Run all ranges\n";
    echo "  -v, --verbose        Verbose output\n";
    echo "  --help               Show this help message\n\n";
    echo "Examples:\n";
    echo "  php aggregate_orchestrated.php --all --verbose\n";
    echo "  php aggregate_orchestrated.php --range daily --date 2025-11-06\n";
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $exitCode = runOrchestratedAggregation();
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n");
        $exitCode = 1;
    }
    
    exit($exitCode);
}
