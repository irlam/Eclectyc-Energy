# Data Aggregation & Analytics Features

## Overview

This document describes the enhanced data aggregation and analytics features implemented for the Eclectyc Energy platform, including automated scheduler orchestration, comparison snapshots, baseload analytics, missing data detection, and external dataset integration.

## Features

### 1. Automated Scheduler Orchestration

**Location:** `app/Domain/Orchestration/`

The scheduler orchestration system provides:
- **Telemetry Service**: Tracks all aggregation executions with detailed metrics
- **Alert Service**: Sends failure and warning alerts via email
- **Orchestrator**: Coordinates aggregation tasks with monitoring and error handling

**Key Components:**
- `SchedulerOrchestrator.php`: Main orchestration logic
- `TelemetryService.php`: Records execution metrics to database
- `AlertService.php`: Handles email alerts for failures and warnings
- `OrchestrationResult.php`: Value object for execution results

**Database Tables:**
- `scheduler_executions`: Tracks all aggregation runs
- `scheduler_alerts`: Stores alert history

**Usage:**
```bash
# Run orchestrated aggregation with full monitoring
php scripts/aggregate_orchestrated.php --all --verbose

# Run specific range with orchestration
php scripts/aggregate_orchestrated.php --range daily --date 2025-11-06

# View help
php scripts/aggregate_orchestrated.php --help
```

### 2. Comparison Snapshots

**Location:** `app/Domain/Comparison/`

Provides period-over-period comparison analysis:
- Day-over-day comparisons
- Week-over-week comparisons
- Month-over-month comparisons
- Year-over-year comparisons

**Key Components:**
- `ComparisonSnapshotService.php`: Main service for fetching comparison data
- `DailyComparisonSnapshot.php`: Daily comparison value object
- `WeeklyComparisonSnapshot.php`: Weekly comparison value object
- `MonthlyComparisonSnapshot.php`: Monthly comparison value object
- `AnnualComparisonSnapshot.php`: Annual comparison value object

**Usage Example:**
```php
use App\Domain\Comparison\ComparisonSnapshotService;

$service = new ComparisonSnapshotService($pdo);
$snapshot = $service->getDailyComparison($meterId, $date);

// Get comparisons
$dayOverDay = $snapshot->getDayOverDayChange();
$weekOverWeek = $snapshot->getWeekOverWeekChange();
$monthOverMonth = $snapshot->getMonthOverMonthChange();
$yearOverYear = $snapshot->getYearOverYearChange();

// Each returns: ['absolute' => float, 'percentage' => float, 'trend' => string]
```

### 3. Baseload Analytics

**Location:** `app/Domain/Analytics/BaseloadAnalyzer.php`

Analyzes energy consumption patterns to identify baseload:
- Calculates minimum constant load
- Estimates baseload percentage of total consumption
- Identifies baseload variations over time

**Usage Example:**
```php
use App\Domain\Analytics\BaseloadAnalyzer;

$analyzer = new BaseloadAnalyzer($pdo);
$baseload = $analyzer->calculateBaseload($meterId, $startDate, $endDate);

// Returns:
// [
//   'meter_id' => int,
//   'baseload_kwh' => float,
//   'baseload_percentage' => float,
//   'average_baseload' => float,
//   'min_baseload' => float,
//   'max_baseload' => float,
//   'total_consumption' => float,
//   'days_analyzed' => int
// ]
```

### 4. Missing Data Detection

**Location:** `app/Domain/Analytics/BaseloadAnalyzer.php::detectDataQualityIssues()`

Detects and reports data quality issues:
- Missing reading periods (for half-hourly meters)
- Zero readings detection
- Negative readings detection
- Statistical outlier detection using IQR method
- Data completeness percentage

**Database Table:**
- `data_quality_issues`: Stores detected issues

**CLI Script:**
```bash
# Run data quality checks for yesterday
php scripts/run_data_quality_checks.php --verbose

# Check specific date
php scripts/run_data_quality_checks.php --date 2025-11-06

# Check specific meter
php scripts/run_data_quality_checks.php --meter 123 --verbose
```

**Usage Example:**
```php
use App\Domain\Analytics\BaseloadAnalyzer;

$analyzer = new BaseloadAnalyzer($pdo);
$issues = $analyzer->detectDataQualityIssues($meterId, $date);

// Returns:
// [
//   'missing_periods' => [1, 5, 12, ...],  // Missing period numbers
//   'anomalies' => [
//     ['type' => 'outlier', 'period' => 15, 'value' => 123.45, 'bounds' => [10, 100]],
//     ['type' => 'negative_value', 'period' => 23, 'value' => -5.2]
//   ],
//   'data_completeness' => 95.8,
//   'zero_readings' => 3,
//   'negative_readings' => 1
// ]
```

### 5. External Dataset Integration

**Location:** `app/Domain/External/ExternalDataService.php`

Integrates external datasets for enhanced analytics and carbon reporting:
- **Temperature Data**: Weather data for heating/cooling correlation
- **Calorific Values**: Gas energy content for accurate gas consumption
- **Carbon Intensity**: Grid carbon emissions for carbon footprint reporting

**Database Tables:**
- `external_temperature_data`: Temperature records by location
- `external_calorific_values`: Gas calorific values by region
- `external_carbon_intensity`: Grid carbon intensity by region

**CLI Script:**
```bash
# Import temperature data
php scripts/import_external_data.php -t temperature -f data/temp.csv -l London

# Import calorific values
php scripts/import_external_data.php -t calorific -f data/cv.csv -r UK_SE

# Import carbon intensity data
php scripts/import_external_data.php -t carbon -f data/carbon.csv -r GB
```

**CSV Formats:**

Temperature CSV:
```
date,avg_temp,min_temp,max_temp
2025-11-01,12.5,8.3,16.2
2025-11-02,13.1,9.0,17.5
```

Calorific Value CSV:
```
date,calorific_value,unit
2025-11-01,39.5,MJ/m3
2025-11-02,39.8,MJ/m3
```

Carbon Intensity CSV:
```
datetime,intensity,forecast,actual
2025-11-01 00:00:00,180.5,175.0,182.3
2025-11-01 00:30:00,175.2,170.0,176.1
```

**Carbon Emissions Calculation:**
```php
use App\Domain\External\ExternalDataService;

$service = new ExternalDataService($pdo);
$emissions = $service->calculateCarbonEmissions($meterId, $startDate, $endDate, 'GB');

// Returns:
// [
//   'meter_id' => int,
//   'start_date' => string,
//   'end_date' => string,
//   'total_emissions_kg_co2' => float,
//   'total_emissions_tonnes_co2' => float,
//   'daily_breakdown' => [
//     ['date' => '2025-11-01', 'consumption_kwh' => 1234.5, 
//      'carbon_intensity' => 180.5, 'emissions_kg_co2' => 222.8],
//     ...
//   ]
// ]
```

## Cron Job Setup

Add these scheduled tasks to automate data processing:

### 1. Daily Orchestrated Aggregation (Recommended)
```bash
# Run at 1:30 AM daily
30 1 * * * /usr/bin/php /path/to/eclectyc-energy/scripts/aggregate_orchestrated.php --all --verbose >> /path/to/logs/aggregation.log 2>&1
```

### 2. Data Quality Checks
```bash
# Run at 2:00 AM daily
0 2 * * * /usr/bin/php /path/to/eclectyc-energy/scripts/run_data_quality_checks.php --verbose >> /path/to/logs/quality.log 2>&1
```

### 3. External Data Import (if automated)
```bash
# Example: Daily temperature import at 3:00 AM
0 3 * * * /usr/bin/php /path/to/eclectyc-energy/scripts/import_external_data.php -t temperature -f /path/to/daily_temp.csv -l London >> /path/to/logs/external.log 2>&1
```

## Configuration

Update `.env` file with the following settings:

```env
# Admin email for alerts
ADMIN_EMAIL=admin@eclectyc.energy

# Enable/disable alerts
ALERT_ENABLED=true

# External data API keys (optional)
WEATHER_API_KEY=your_key_here
CARBON_API_URL=https://api.carbonintensity.org.uk
```

## Database Migration

Run the new migration to create required tables:

```bash
# Run migrations
php scripts/migrate.php

# Or via browser (if MIGRATION_KEY is set)
https://your-domain/scripts/migrate.php?key=YOUR_MIGRATION_KEY
```

The migration file `003_add_analytics_tables.sql` creates:
- `scheduler_executions`
- `scheduler_alerts`
- `external_temperature_data`
- `external_calorific_values`
- `external_carbon_intensity`
- `data_quality_issues`
- `comparison_snapshots`
- `ai_insights`

## API Integration Examples

### Getting Comparison Data
```php
$service = new ComparisonSnapshotService($pdo);
$snapshot = $service->getDailyComparison($meterId, new DateTimeImmutable('2025-11-06'));

// Access data
$current = $snapshot->getCurrent();
$comparisons = $snapshot->toArray()['comparisons'];
```

### Calculating Baseload
```php
$analyzer = new BaseloadAnalyzer($pdo);
$baseload = $analyzer->calculateBaseload(
    $meterId,
    new DateTimeImmutable('2025-10-01'),
    new DateTimeImmutable('2025-10-31')
);

echo "Baseload: {$baseload['baseload_kwh']} kWh ({$baseload['baseload_percentage']}%)";
```

### Carbon Reporting
```php
$externalData = new ExternalDataService($pdo);
$emissions = $externalData->calculateCarbonEmissions(
    $meterId,
    new DateTimeImmutable('2025-11-01'),
    new DateTimeImmutable('2025-11-30'),
    'GB'
);

echo "Total emissions: {$emissions['total_emissions_tonnes_co2']} tonnes CO2";
```

## Monitoring & Alerts

### Viewing Execution History
```php
$telemetry = new TelemetryService($pdo);
$recent = $telemetry->getRecentExecutions(50);

foreach ($recent as $execution) {
    echo "{$execution['range_type']}: {$execution['status']} - {$execution['duration_seconds']}s\n";
}
```

### Viewing Statistics
```php
$stats = $telemetry->getStatistics(
    new DateTimeImmutable('2025-11-01'),
    new DateTimeImmutable('2025-11-30')
);

foreach ($stats as $stat) {
    echo "{$stat['range_type']}: {$stat['successful']}/{$stat['total_executions']} successful\n";
}
```

## Troubleshooting

### No Alerts Being Sent
1. Check `MAIL_HOST` is configured in `.env`
2. Verify `ADMIN_EMAIL` is set
3. Check email credentials are correct
4. Review `scheduler_alerts` table for logged alerts

### Missing External Data
1. Verify CSV format matches expected structure
2. Check import script output for errors
3. Query external data tables directly
4. Ensure location/region names match between systems

### Data Quality Issues Not Detected
1. Verify script is running via cron
2. Check `data_quality_issues` table for entries
3. Run script manually with `--verbose` flag
4. Ensure meter data exists for the target date

## Future Enhancements

Planned additions to the analytics system:
- AI-powered anomaly detection using ML models
- Automated carbon intensity data fetching from APIs
- Weather API integration for automatic temperature import
- Predictive analytics for consumption forecasting
- Advanced correlation analysis with external factors
- Real-time alerting via SMS/webhook
- Dashboard widgets for analytics visualization

## Support

For questions or issues with the analytics features:
1. Check this documentation
2. Review logs in `logs/` directory
3. Query telemetry tables for execution history
4. Review STATUS.md for known issues
