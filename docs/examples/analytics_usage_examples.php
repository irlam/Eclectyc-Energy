<?php
/**
 * eclectyc-energy/docs/examples/analytics_usage_examples.php
 * Examples demonstrating the use of new analytics features.
 * This is a documentation file - not meant to be executed directly.
 */

// Example 1: Using the Orchestrated Aggregation
// ==================================================

use App\Config\Database;
use App\Domain\Orchestration\SchedulerOrchestrator;
use App\Domain\Orchestration\TelemetryService;
use App\Domain\Orchestration\AlertService;

$pdo = Database::getConnection();

$telemetry = new TelemetryService($pdo);
$alertService = new AlertService($pdo, ['admin_email' => 'admin@example.com']);
$orchestrator = new SchedulerOrchestrator($pdo, $telemetry, $alertService);

// Execute a single aggregation with monitoring
$result = $orchestrator->executeAggregation('daily', new DateTimeImmutable('2025-11-06'));

if ($result->isSuccess()) {
    echo "Aggregation completed successfully!\n";
    echo "Execution ID: {$result->getExecutionId()}\n";
    echo "Duration: {$result->getDuration()}s\n";
    echo "Meters processed: {$result->getMetricsProcessed()}\n";
} else {
    echo "Aggregation failed: {$result->getErrorMessage()}\n";
}

// Execute all ranges with monitoring
$results = $orchestrator->executeAllRanges(new DateTimeImmutable('2025-11-06'));


// Example 2: Using Comparison Snapshots
// ==================================================

use App\Domain\Comparison\ComparisonSnapshotService;

$comparisonService = new ComparisonSnapshotService($pdo);

// Get daily comparison for a meter
$snapshot = $comparisonService->getDailyComparison(123, new DateTimeImmutable('2025-11-06'));

// Access current data
$current = $snapshot->getCurrent();
echo "Current consumption: {$current['total_consumption']} kWh\n";

// Get day-over-day change
$dayChange = $snapshot->getDayOverDayChange();
echo "Day-over-day change: {$dayChange['percentage']}% ({$dayChange['trend']})\n";

// Get week-over-week change
$weekChange = $snapshot->getWeekOverWeekChange();
echo "Week-over-week change: {$weekChange['percentage']}%\n";

// Get month-over-month change
$monthChange = $snapshot->getMonthOverMonthChange();
echo "Month-over-month change: {$monthChange['percentage']}%\n";

// Get year-over-year change
$yearChange = $snapshot->getYearOverYearChange();
echo "Year-over-year change: {$yearChange['percentage']}%\n";

// Get all comparisons as array
$allData = $snapshot->toArray();


// Example 3: Baseload Analytics
// ==================================================

use App\Domain\Analytics\BaseloadAnalyzer;

$analyzer = new BaseloadAnalyzer($pdo);

// Calculate baseload for a meter over a month
$baseload = $analyzer->calculateBaseload(
    meterId: 123,
    startDate: new DateTimeImmutable('2025-10-01'),
    endDate: new DateTimeImmutable('2025-10-31')
);

echo "Baseload Analysis Results:\n";
echo "- Estimated Baseload: {$baseload['baseload_kwh']} kWh\n";
echo "- Baseload Percentage: {$baseload['baseload_percentage']}%\n";
echo "- Average Daily Baseload: {$baseload['average_baseload']} kWh\n";
echo "- Total Consumption: {$baseload['total_consumption']} kWh\n";
echo "- Days Analyzed: {$baseload['days_analyzed']}\n";

// Identify potential savings
$totalConsumption = $baseload['total_consumption'];
$baseloadConsumption = $baseload['baseload_kwh'] * $baseload['days_analyzed'];
$variableConsumption = $totalConsumption - $baseloadConsumption;
echo "\nConsumption Breakdown:\n";
echo "- Baseload (constant): {$baseloadConsumption} kWh\n";
echo "- Variable (activity): {$variableConsumption} kWh\n";


// Example 4: Data Quality Detection
// ==================================================

use App\Domain\Analytics\BaseloadAnalyzer;

$analyzer = new BaseloadAnalyzer($pdo);

// Check data quality for a specific date
$issues = $analyzer->detectDataQualityIssues(123, new DateTimeImmutable('2025-11-06'));

echo "Data Quality Report:\n";
echo "- Completeness: {$issues['data_completeness']}%\n";
echo "- Zero Readings: {$issues['zero_readings']}\n";
echo "- Negative Readings: {$issues['negative_readings']}\n";

if (!empty($issues['missing_periods'])) {
    echo "- Missing Periods: " . implode(', ', $issues['missing_periods']) . "\n";
}

if (!empty($issues['anomalies'])) {
    echo "- Anomalies Detected: " . count($issues['anomalies']) . "\n";
    foreach ($issues['anomalies'] as $anomaly) {
        echo "  * {$anomaly['type']} at period {$anomaly['period']}: {$anomaly['value']}\n";
    }
}


// Example 5: External Data Integration
// ==================================================

use App\Domain\External\ExternalDataService;

$externalData = new ExternalDataService($pdo);

// Store temperature data
$externalData->storeTemperatureData(
    location: 'London',
    date: new DateTimeImmutable('2025-11-06'),
    data: [
        'avg_temperature' => 12.5,
        'min_temperature' => 8.3,
        'max_temperature' => 16.2,
        'source' => 'met_office'
    ]
);

// Retrieve temperature data
$temps = $externalData->getTemperatureData(
    location: 'London',
    startDate: new DateTimeImmutable('2025-11-01'),
    endDate: new DateTimeImmutable('2025-11-06')
);

foreach ($temps as $temp) {
    echo "{$temp['date']}: {$temp['avg_temperature']}°C\n";
}

// Store calorific values
$externalData->storeCalorificValues(
    region: 'UK_SE',
    date: new DateTimeImmutable('2025-11-06'),
    data: [
        'calorific_value' => 39.5,
        'unit' => 'MJ/m3',
        'source' => 'national_grid'
    ]
);

// Store carbon intensity
$externalData->storeCarbonIntensity(
    region: 'GB',
    datetime: new DateTimeImmutable('2025-11-06 12:00:00'),
    data: [
        'intensity' => 180.5,
        'forecast' => 175.0,
        'actual' => 182.3,
        'source' => 'national_grid_eso'
    ]
);


// Example 6: Carbon Emissions Calculation
// ==================================================

use App\Domain\External\ExternalDataService;

$externalData = new ExternalDataService($pdo);

// Calculate carbon emissions for a meter
$emissions = $externalData->calculateCarbonEmissions(
    meterId: 123,
    startDate: new DateTimeImmutable('2025-11-01'),
    endDate: new DateTimeImmutable('2025-11-30'),
    region: 'GB'
);

echo "Carbon Emissions Report:\n";
echo "- Meter ID: {$emissions['meter_id']}\n";
echo "- Period: {$emissions['start_date']} to {$emissions['end_date']}\n";
echo "- Total Emissions: {$emissions['total_emissions_kg_co2']} kg CO2\n";
echo "- Total Emissions: {$emissions['total_emissions_tonnes_co2']} tonnes CO2\n";

echo "\nDaily Breakdown:\n";
foreach ($emissions['daily_breakdown'] as $day) {
    echo "  {$day['date']}: {$day['consumption_kwh']} kWh × {$day['carbon_intensity']} gCO2/kWh = {$day['emissions_kg_co2']} kg CO2\n";
}


// Example 7: Telemetry and Statistics
// ==================================================

use App\Domain\Orchestration\TelemetryService;

$telemetry = new TelemetryService($pdo);

// Get recent execution history
$recent = $telemetry->getRecentExecutions(10);

echo "Recent Aggregation Executions:\n";
foreach ($recent as $execution) {
    echo "- [{$execution['range_type']}] {$execution['status']}: ";
    echo "{$execution['meters_processed']} meters in {$execution['duration_seconds']}s\n";
}

// Get statistics for a period
$stats = $telemetry->getStatistics(
    startDate: new DateTimeImmutable('2025-11-01'),
    endDate: new DateTimeImmutable('2025-11-30')
);

echo "\nAggregation Statistics (November 2025):\n";
foreach ($stats as $stat) {
    echo "- {$stat['range_type']}:\n";
    echo "  * Total Executions: {$stat['total_executions']}\n";
    echo "  * Successful: {$stat['successful']}\n";
    echo "  * Failed: {$stat['failed']}\n";
    echo "  * Avg Duration: {$stat['avg_duration']}s\n";
    echo "  * Total Meters: {$stat['total_meters_processed']}\n";
}


// Example 8: Building a Dashboard Widget
// ==================================================

// Combine multiple features for a comprehensive dashboard

function getMeterInsights(PDO $pdo, int $meterId, DateTimeImmutable $date): array
{
    $comparisonService = new ComparisonSnapshotService($pdo);
    $analyzer = new BaseloadAnalyzer($pdo);
    $externalData = new ExternalDataService($pdo);
    
    // Get comparison data
    $snapshot = $comparisonService->getDailyComparison($meterId, $date);
    
    // Get baseload analysis
    $monthStart = $date->modify('first day of this month');
    $monthEnd = $date->modify('last day of this month');
    $baseload = $analyzer->calculateBaseload($meterId, $monthStart, $monthEnd);
    
    // Check data quality
    $quality = $analyzer->detectDataQualityIssues($meterId, $date);
    
    // Calculate carbon emissions (if carbon data available)
    $emissions = $externalData->calculateCarbonEmissions($meterId, $date, $date, 'GB');
    
    return [
        'current_consumption' => $snapshot->getCurrentConsumption(),
        'day_over_day_change' => $snapshot->getDayOverDayChange(),
        'week_over_week_change' => $snapshot->getWeekOverWeekChange(),
        'baseload_percentage' => $baseload['baseload_percentage'],
        'data_quality' => $quality['data_completeness'],
        'daily_emissions' => $emissions['total_emissions_kg_co2'] ?? 0,
        'has_issues' => !empty($quality['anomalies']) || !empty($quality['missing_periods']),
    ];
}

// Use the insights
$insights = getMeterInsights($pdo, 123, new DateTimeImmutable('2025-11-06'));

echo "Meter Dashboard:\n";
echo "Current Consumption: {$insights['current_consumption']} kWh\n";
echo "Day Change: {$insights['day_over_day_change']['percentage']}%\n";
echo "Week Change: {$insights['week_over_week_change']['percentage']}%\n";
echo "Baseload: {$insights['baseload_percentage']}%\n";
echo "Data Quality: {$insights['data_quality']}%\n";
echo "Daily CO2: {$insights['daily_emissions']} kg\n";
echo "Status: " . ($insights['has_issues'] ? 'Issues Detected' : 'OK') . "\n";
