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

    foreach ($ranges as $range) {
        try {
            switch ($range) {
                case 'daily':
                    $dailyAggregator ??= new DailyAggregator($pdo);
                    $dailySummary = $dailyAggregator->aggregate($targetDate);
                    aggregationLogAudit($pdo, 'daily', $dailySummary->toArray());
                    if ($verbose) {
                        aggregationOutputDailySummary($dailySummary);
                    }
                    if ($dailySummary->getErrors() > 0) {
                        $exitCode = 1;
                    }
                    break;

                case 'weekly':
                    $periodAggregator ??= new PeriodAggregator($pdo);
                    [$start, $end] = aggregationResolveWeeklyRange($targetDate);
                    $weeklySummary = $periodAggregator->aggregate('weekly', $start, $end);
                    aggregationLogAudit($pdo, 'weekly', $weeklySummary->toArray());
                    if ($verbose) {
                        aggregationOutputPeriodSummary($weeklySummary);
                    }
                    if ($weeklySummary->getErrors() > 0) {
                        $exitCode = 1;
                    }
                    break;

                case 'monthly':
                    $periodAggregator ??= $periodAggregator ?? new PeriodAggregator($pdo);
                    [$start, $end] = aggregationResolveMonthlyRange($targetDate);
                    $monthlySummary = $periodAggregator->aggregate('monthly', $start, $end);
                    aggregationLogAudit($pdo, 'monthly', $monthlySummary->toArray());
                    if ($verbose) {
                        aggregationOutputPeriodSummary($monthlySummary);
                    }
                    if ($monthlySummary->getErrors() > 0) {
                        $exitCode = 1;
                    }
                    break;

                case 'annual':
                    $periodAggregator ??= $periodAggregator ?? new PeriodAggregator($pdo);
                    [$start, $end] = aggregationResolveAnnualRange($targetDate);
                    $annualSummary = $periodAggregator->aggregate('annual', $start, $end);
                    aggregationLogAudit($pdo, 'annual', $annualSummary->toArray());
                    if ($verbose) {
                        aggregationOutputPeriodSummary($annualSummary);
                    }
                    if ($annualSummary->getErrors() > 0) {
                        $exitCode = 1;
                    }
                    break;
            }
        } catch (Throwable $exception) {
            if ($verbose) {
                fwrite(STDERR, sprintf("Error running %s aggregation: %s\n", $range, $exception->getMessage()));
            }
            $exitCode = 1;
        }
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