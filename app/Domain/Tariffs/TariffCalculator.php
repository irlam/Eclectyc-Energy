<?php
/**
 * eclectyc-energy/app/Domain/Tariffs/TariffCalculator.php
 * Calculates energy costs based on tariff structures and consumption data.
 */

namespace App\Domain\Tariffs;

use PDO;
use DateTimeImmutable;

class TariffCalculator
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Calculate cost for a meter over a date range using its tariff
     * 
     * @param int $meterId Meter ID
     * @param DateTimeImmutable $startDate Start date
     * @param DateTimeImmutable $endDate End date
     * @return TariffCalculationResult Calculation results
     */
    public function calculateCost(int $meterId, DateTimeImmutable $startDate, DateTimeImmutable $endDate): TariffCalculationResult
    {
        $result = new TariffCalculationResult($meterId, $startDate, $endDate);

        try {
            $tariff = $this->getTariffForMeter($meterId);
            if (!$tariff) {
                $result->registerError('No tariff found for meter');
                return $result;
            }

            $aggregations = $this->getAggregations($meterId, $startDate, $endDate);

            foreach ($aggregations as $agg) {
                $peakCost = ($agg['peak_consumption'] ?? 0.0) * ($tariff['peak_rate'] ?? 0.0);
                $offPeakCost = ($agg['off_peak_consumption'] ?? 0.0) * ($tariff['off_peak_rate'] ?? 0.0);
                
                $result->addDailyCost($agg['date'], $peakCost + $offPeakCost, $peakCost, $offPeakCost);
            }
        } catch (\Exception $e) {
            $result->registerError($e->getMessage());
        }

        return $result;
    }

    /**
     * Get the active tariff for a meter
     * 
     * @param int $meterId Meter ID
     * @return array|null Tariff data or null if not found
     */
    private function getTariffForMeter(int $meterId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT t.* 
            FROM tariffs t
            INNER JOIN meters m ON m.tariff_id = t.id
            WHERE m.id = :meter_id AND t.is_active = TRUE
            LIMIT 1
        ');
        $stmt->execute(['meter_id' => $meterId]);
        $result = $stmt->fetch();
        return $result ?: null;
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
            SELECT date, peak_consumption, off_peak_consumption, total_consumption
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
        return $stmt->fetchAll() ?: [];
    }
}
