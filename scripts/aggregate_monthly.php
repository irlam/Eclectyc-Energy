<?php
/**
 * eclectyc-energy/scripts/aggregate_monthly.php
 * Convenience wrapper for running the monthly aggregation job.
 * Last updated: 06/11/2025
 */

require_once __DIR__ . '/aggregate_cron.php';

$options = getopt('d:v', ['date:', 'verbose']);
$dateArgument = $options['d'] ?? $options['date'] ?? null;
$verbose = isset($options['v']) || isset($options['verbose']);

try {
    $targetDate = aggregationParseTargetDate($dateArgument);
    exit(runAggregations(['monthly'], $targetDate, $verbose));
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
