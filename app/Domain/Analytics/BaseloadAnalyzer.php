<?php
/**
 * eclectyc-energy/app/Domain/Analytics/BaseloadAnalyzer.php
 * Analyzes consumption patterns to identify baseload (constant background load).
 */

namespace App\Domain\Analytics;

use PDO;
use DateTimeImmutable;

class BaseloadAnalyzer
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Analyze baseload for a meter over a date range
     * 
     * @param int $meterId Meter ID
     * @param DateTimeImmutable $startDate Start date
     * @param DateTimeImmutable $endDate End date
     * @return BaseloadAnalysisResult Analysis results
     */
    public function analyze(int $meterId, DateTimeImmutable $startDate, DateTimeImmutable $endDate): BaseloadAnalysisResult
    {
        $result = new BaseloadAnalysisResult($meterId, $startDate, $endDate);

        try {
            $aggregations = $this->getAggregations($meterId, $startDate, $endDate);

            if (empty($aggregations)) {
                $result->registerError('No data available for analysis');
                return $result;
            }

            // Calculate baseload as minimum daily consumption
            $minConsumption = null;
            $avgConsumption = 0.0;
            $totalConsumption = 0.0;
            $count = 0;

            foreach ($aggregations as $agg) {
                $consumption = $agg['total_consumption'] ?? 0.0;
                $totalConsumption += $consumption;
                $count++;

                if ($minConsumption === null || $consumption < $minConsumption) {
                    $minConsumption = $consumption;
                }
            }

            $avgConsumption = $count > 0 ? $totalConsumption / $count : 0.0;
            $estimatedBaseload = $minConsumption ?? 0.0;

            // Calculate percentage of baseload vs average
            $baseloadPercentage = $avgConsumption > 0 ? ($estimatedBaseload / $avgConsumption) * 100 : 0.0;

            $result->setBaseload($estimatedBaseload);
            $result->setAverageConsumption($avgConsumption);
            $result->setBaseloadPercentage($baseloadPercentage);
            $result->setDataPointCount($count);
        } catch (\Exception $e) {
            $result->registerError($e->getMessage());
        }

        return $result;
    }

    /**
     * Get aggregated consumption data for a meter in a date range
     * 
     * @param int $meterId Meter ID
     * @param DateTimeImmutable $startDate Start date
     * @param DateTimeImmutable $endDate End date
     * @return array Aggregation records
     */
    private function getAggregations(int $meterId, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        $stmt = $this->pdo->prepare('
            SELECT date, total_consumption, min_reading, max_reading
            FROM daily_aggregations
            WHERE meter_id = :meter_id 
              AND date BETWEEN :start_date AND :end_date
              AND total_consumption > 0
            ORDER BY date
        ');
        $stmt->execute([
            'meter_id' => $meterId,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ]);
        return $stmt->fetchAll() ?: [];
    }
}
