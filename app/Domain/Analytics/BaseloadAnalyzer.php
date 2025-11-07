<?php
/**
 * eclectyc-energy/app/Domain/Analytics/BaseloadAnalyzer.php
 * Analyzes energy consumption patterns to identify baseload and detect anomalies.
 * Provides insights for energy optimization and efficiency improvements.
 */

namespace App\Domain\Analytics;

use PDO;
use PDOException;
use DateTimeImmutable;

class BaseloadAnalyzer
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Calculate baseload consumption for a meter over a period.
     * 
     * @param int $meterId Meter identifier
     * @param DateTimeImmutable $startDate Start date for analysis
     * @param DateTimeImmutable $endDate End date for analysis
     * @return array Baseload analysis results
     */
    public function calculateBaseload(int $meterId, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        // Get daily aggregations for the period
        $stmt = $this->pdo->prepare('
            SELECT 
                date,
                total_consumption,
                min_reading,
                max_reading,
                reading_count
            FROM daily_aggregations
            WHERE meter_id = :meter_id 
              AND date BETWEEN :start_date AND :end_date
            ORDER BY date
        ');
        
        $stmt->execute([
            'meter_id' => $meterId,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ]);
        
        $dailyData = $stmt->fetchAll() ?: [];
        
        if (empty($dailyData)) {
            return [
                'meter_id' => $meterId,
                'baseload_kwh' => 0.0,
                'baseload_percentage' => 0.0,
                'average_baseload' => 0.0,
                'min_baseload' => 0.0,
                'max_baseload' => 0.0,
                'total_consumption' => 0.0,
            ];
        }
        
        // Calculate baseload as the minimum daily consumption (proxy for constant load)
        $minDailyConsumptions = array_column($dailyData, 'total_consumption');
        $minBaseload = min($minDailyConsumptions);
        $avgBaseload = array_sum($minDailyConsumptions) / count($minDailyConsumptions);
        $totalConsumption = array_sum(array_column($dailyData, 'total_consumption'));
        
        // Estimate baseload as the lowest 10th percentile
        sort($minDailyConsumptions);
        $percentile10Index = (int) floor(count($minDailyConsumptions) * 0.1);
        $baseloadEstimate = $minDailyConsumptions[$percentile10Index] ?? $minBaseload;
        
        // Calculate baseload percentage
        $baseloadPercentage = $totalConsumption > 0 
            ? ($baseloadEstimate * count($dailyData) / $totalConsumption) * 100 
            : 0.0;
        
        return [
            'meter_id' => $meterId,
            'baseload_kwh' => $baseloadEstimate,
            'baseload_percentage' => round($baseloadPercentage, 2),
            'average_baseload' => round($avgBaseload, 3),
            'min_baseload' => round($minBaseload, 3),
            'max_baseload' => round(max($minDailyConsumptions), 3),
            'total_consumption' => round($totalConsumption, 3),
            'days_analyzed' => count($dailyData),
        ];
    }

    /**
     * Detect missing or anomalous data in meter readings.
     * 
     * @param int $meterId Meter identifier
     * @param DateTimeImmutable $date Date to check
     * @return array List of detected issues
     */
    public function detectDataQualityIssues(int $meterId, DateTimeImmutable $date): array
    {
        $issues = [
            'missing_periods' => [],
            'anomalies' => [],
            'data_completeness' => 0.0,
            'zero_readings' => 0,
            'negative_readings' => 0,
        ];
        
        // Check if meter is half-hourly
        $meterStmt = $this->pdo->prepare('SELECT is_half_hourly FROM meters WHERE id = :meter_id');
        $meterStmt->execute(['meter_id' => $meterId]);
        $meter = $meterStmt->fetch();
        
        if (!$meter) {
            return $issues;
        }
        
        $isHalfHourly = $meter['is_half_hourly'] ?? false;
        $expectedReadings = $isHalfHourly ? 48 : 1;
        
        // Get readings for the date
        $stmt = $this->pdo->prepare('
            SELECT 
                reading_time,
                period_number,
                reading_value
            FROM meter_readings
            WHERE meter_id = :meter_id 
              AND reading_date = :date
            ORDER BY reading_time, period_number
        ');
        
        $stmt->execute([
            'meter_id' => $meterId,
            'date' => $date->format('Y-m-d'),
        ]);
        
        $readings = $stmt->fetchAll() ?: [];
        $actualReadings = count($readings);
        
        // Calculate data completeness
        $issues['data_completeness'] = $expectedReadings > 0 
            ? round(($actualReadings / $expectedReadings) * 100, 2) 
            : 0.0;
        
        // Detect missing periods for half-hourly meters
        if ($isHalfHourly && $actualReadings < $expectedReadings) {
            $presentPeriods = array_column($readings, 'period_number');
            for ($period = 1; $period <= 48; $period++) {
                if (!in_array($period, $presentPeriods)) {
                    $issues['missing_periods'][] = $period;
                }
            }
        }
        
        // Detect anomalies
        foreach ($readings as $reading) {
            $value = (float) $reading['reading_value'];
            
            // Zero readings
            if ($value == 0) {
                $issues['zero_readings']++;
            }
            
            // Negative readings (should not happen)
            if ($value < 0) {
                $issues['negative_readings']++;
                $issues['anomalies'][] = [
                    'type' => 'negative_value',
                    'period' => $reading['period_number'],
                    'value' => $value,
                ];
            }
        }
        
        // Statistical outlier detection (using IQR method)
        if (count($readings) >= 10) {
            $values = array_map(fn($r) => (float) $r['reading_value'], $readings);
            sort($values);
            
            $q1Index = (int) floor(count($values) * 0.25);
            $q3Index = (int) floor(count($values) * 0.75);
            
            $q1 = $values[$q1Index];
            $q3 = $values[$q3Index];
            $iqr = $q3 - $q1;
            
            $lowerBound = $q1 - (1.5 * $iqr);
            $upperBound = $q3 + (1.5 * $iqr);
            
            foreach ($readings as $reading) {
                $value = (float) $reading['reading_value'];
                if ($value < $lowerBound || $value > $upperBound) {
                    $issues['anomalies'][] = [
                        'type' => 'outlier',
                        'period' => $reading['period_number'],
                        'value' => $value,
                        'bounds' => [$lowerBound, $upperBound],
                    ];
                }
            }
        }
        
        return $issues;
    }

    /**
     * Analyze consumption patterns with external factors.
     * 
     * @param int $meterId Meter identifier
     * @param DateTimeImmutable $startDate Start date for analysis
     * @param DateTimeImmutable $endDate End date for analysis
     * @param array $externalFactors External data (e.g., temperature, occupancy)
     * @return array Correlation analysis results
     */
    public function correlateWithExternalFactors(int $meterId, DateTimeImmutable $startDate, DateTimeImmutable $endDate, array $externalFactors = []): array
    {
        // Placeholder implementation
        // Future enhancement: Analyze correlations with:
        // - External temperature
        // - Building occupancy
        // - Production schedules
        // - Weather conditions
        // - Special events/holidays
        
        return [
            'correlations' => [],
            'insights' => [],
            'recommendations' => [],
        ];
    }

    /**
     * Compare consumption across multiple sites or meters.
     * 
     * @param array $meterIds Array of meter identifiers to compare
     * @param DateTimeImmutable $startDate Start date for comparison
     * @param DateTimeImmutable $endDate End date for comparison
     * @return array Comparative analysis results
     */
    public function comparativeAnalysis(array $meterIds, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        // Placeholder implementation
        // Future enhancement: Enable group-level comparisons:
        // - Benchmark against peer sites
        // - Identify high/low performers
        // - Normalize for size/activity
        // - Trend analysis
        // - Best practice identification
        
        return [
            'comparisons' => [],
            'benchmarks' => [],
            'outliers' => [],
            'recommendations' => [],
        ];
    }

    /**
     * Generate AI-driven insights and recommendations.
     * 
     * @param int $meterId Meter identifier
     * @param array $analysisData Historical and current analysis data
     * @return array AI-generated insights
     */
    public function generateInsights(int $meterId, array $analysisData): array
    {
        // Placeholder implementation
        // Future enhancement: AI-powered analytics:
        // - Pattern recognition
        // - Predictive modeling
        // - Optimization recommendations
        // - Anomaly explanations
        // - Cost-saving opportunities
        
        return [
            'insights' => [],
            'predictions' => [],
            'recommendations' => [],
            'confidence_scores' => [],
        ];
    }
}
