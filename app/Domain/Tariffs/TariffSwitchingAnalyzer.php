<?php
/**
 * eclectyc-energy/app/Domain/Tariffs/TariffSwitchingAnalyzer.php
 * Analyzes tariff switching opportunities and provides recommendations.
 * Last updated: 07/11/2025
 */

namespace App\Domain\Tariffs;

use PDO;
use PDOException;
use DateTimeImmutable;

class TariffSwitchingAnalyzer
{
    private PDO $pdo;
    private TariffCalculator $calculator;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->calculator = new TariffCalculator($pdo);
    }

    /**
     * Analyze switching opportunities for a meter based on consumption history.
     *
     * @param int $meterId Meter identifier
     * @param int $currentTariffId Current tariff being used
     * @param string $startDate Start date for consumption analysis (YYYY-MM-DD)
     * @param string $endDate End date for consumption analysis (YYYY-MM-DD)
     * @param string|null $energyType Optional energy type filter ('electricity' or 'gas')
     * @return array{current: array, alternatives: array, recommendation: array|null}
     */
    public function analyzeSwitchingOpportunities(
        int $meterId,
        int $currentTariffId,
        string $startDate,
        string $endDate,
        ?string $energyType = null
    ): array {
        // Get consumption data for the meter
        $consumption = $this->getConsumptionData($meterId, $startDate, $endDate);
        
        if (empty($consumption)) {
            return [
                'current' => [],
                'alternatives' => [],
                'recommendation' => null,
                'error' => 'No consumption data available for the specified period',
            ];
        }

        // Calculate current tariff cost
        $currentCost = $this->calculateTariffCost($currentTariffId, $consumption);

        // Get all active tariffs for comparison
        $alternativeTariffs = $this->getAlternativeTariffs($currentTariffId, $energyType);

        // Calculate costs for each alternative tariff
        $alternatives = [];
        foreach ($alternativeTariffs as $tariff) {
            $cost = $this->calculateTariffCost($tariff['id'], $consumption);
            $savings = $currentCost['total_cost'] - $cost['total_cost'];
            $savingsPercent = $currentCost['total_cost'] > 0 
                ? ($savings / $currentCost['total_cost']) * 100 
                : 0;

            $alternatives[] = [
                'tariff_id' => $tariff['id'],
                'tariff_name' => $tariff['name'],
                'tariff_code' => $tariff['code'],
                'supplier_name' => $tariff['supplier_name'],
                'tariff_type' => $tariff['tariff_type'],
                'unit_rate' => $tariff['unit_rate'],
                'standing_charge' => $tariff['standing_charge'],
                'peak_rate' => $tariff['peak_rate'],
                'off_peak_rate' => $tariff['off_peak_rate'],
                'weekend_rate' => $tariff['weekend_rate'],
                'total_cost' => $cost['total_cost'],
                'unit_cost' => $cost['unit_cost'],
                'standing_charge_cost' => $cost['standing_charge_cost'],
                'potential_savings' => $savings,
                'savings_percent' => round($savingsPercent, 2),
            ];
        }

        // Sort alternatives by potential savings (highest first)
        usort($alternatives, function ($a, $b) {
            return $b['potential_savings'] <=> $a['potential_savings'];
        });

        // Find the best recommendation
        $recommendation = $this->findBestRecommendation($alternatives, $currentCost);

        return [
            'current' => $currentCost,
            'alternatives' => $alternatives,
            'recommendation' => $recommendation,
            'analysis_period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_consumption' => $consumption['total_consumption'],
                'days_analyzed' => $consumption['days_analyzed'],
            ],
        ];
    }

    /**
     * Get detailed switching analysis for a specific meter and period.
     *
     * @param int $meterId Meter identifier
     * @param string $analysisDate Reference date for analysis (defaults to current date)
     * @return array Detailed switching analysis
     */
    public function getDetailedAnalysis(int $meterId, string $analysisDate = 'now'): array
    {
        $meter = $this->getMeter($meterId);
        
        if (!$meter) {
            return [
                'error' => 'Meter not found',
            ];
        }

        // Determine analysis period (last 90 days)
        $endDate = new DateTimeImmutable($analysisDate);
        $startDate = $endDate->modify('-90 days');

        // Get current tariff for meter
        $currentTariffId = $this->getCurrentTariffForMeter($meterId);
        
        if (!$currentTariffId) {
            return [
                'error' => 'No current tariff assigned to meter',
                'meter' => $meter,
            ];
        }

        return $this->analyzeSwitchingOpportunities(
            $meterId,
            $currentTariffId,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d'),
            $meter['meter_type']
        );
    }

    /**
     * Save switching analysis results to database for historical tracking.
     *
     * @param int $meterId Meter identifier
     * @param array $analysis Analysis results
     * @param int|null $userId User who requested the analysis
     * @return int|null Analysis ID if saved successfully
     */
    public function saveAnalysis(int $meterId, array $analysis, ?int $userId = null): ?int
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO tariff_switching_analyses (
                    meter_id,
                    current_tariff_id,
                    recommended_tariff_id,
                    analysis_start_date,
                    analysis_end_date,
                    current_cost,
                    recommended_cost,
                    potential_savings,
                    savings_percent,
                    analysis_data,
                    analyzed_by,
                    created_at
                )
                VALUES (
                    :meter_id,
                    :current_tariff_id,
                    :recommended_tariff_id,
                    :analysis_start_date,
                    :analysis_end_date,
                    :current_cost,
                    :recommended_cost,
                    :potential_savings,
                    :savings_percent,
                    :analysis_data,
                    :analyzed_by,
                    NOW()
                )
            ');

            $stmt->execute([
                'meter_id' => $meterId,
                'current_tariff_id' => $analysis['current']['tariff_id'] ?? null,
                'recommended_tariff_id' => $analysis['recommendation']['tariff_id'] ?? null,
                'analysis_start_date' => $analysis['analysis_period']['start_date'] ?? null,
                'analysis_end_date' => $analysis['analysis_period']['end_date'] ?? null,
                'current_cost' => $analysis['current']['total_cost'] ?? 0,
                'recommended_cost' => $analysis['recommendation']['total_cost'] ?? 0,
                'potential_savings' => $analysis['recommendation']['potential_savings'] ?? 0,
                'savings_percent' => $analysis['recommendation']['savings_percent'] ?? 0,
                'analysis_data' => json_encode($analysis),
                'analyzed_by' => $userId,
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log('Failed to save switching analysis: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get historical switching analyses for a meter.
     *
     * @param int $meterId Meter identifier
     * @param int $limit Maximum number of results
     * @return array List of historical analyses
     */
    public function getHistoricalAnalyses(int $meterId, int $limit = 10): array
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT 
                    tsa.*,
                    ct.name as current_tariff_name,
                    rt.name as recommended_tariff_name,
                    u.name as analyzed_by_name
                FROM tariff_switching_analyses tsa
                LEFT JOIN tariffs ct ON tsa.current_tariff_id = ct.id
                LEFT JOIN tariffs rt ON tsa.recommended_tariff_id = rt.id
                LEFT JOIN users u ON tsa.analyzed_by = u.id
                WHERE tsa.meter_id = :meter_id
                ORDER BY tsa.created_at DESC
                LIMIT :limit
            ');
            
            $stmt->bindValue(':meter_id', $meterId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('Failed to get historical analyses: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get consumption data for a meter within a date range.
     */
    private function getConsumptionData(int $meterId, string $startDate, string $endDate): array
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT 
                    SUM(reading_value) as total_consumption,
                    COUNT(DISTINCT reading_date) as days_with_data,
                    DATEDIFF(?, ?) + 1 as days_in_period,
                    AVG(reading_value) as avg_daily_consumption
                FROM meter_readings
                WHERE meter_id = ?
                AND reading_date BETWEEN ? AND ?
            ');

            $stmt->execute([
                $endDate,
                $startDate,
                $meterId,
                $startDate,
                $endDate,
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result || $result['total_consumption'] === null) {
                return [];
            }

            return [
                'total_consumption' => (float) $result['total_consumption'],
                'days_analyzed' => (int) $result['days_in_period'],
                'days_with_data' => (int) $result['days_with_data'],
                'avg_daily_consumption' => (float) $result['avg_daily_consumption'],
            ];
        } catch (PDOException $e) {
            error_log('Failed to get consumption data: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate total cost for a tariff based on consumption data.
     */
    private function calculateTariffCost(int $tariffId, array $consumption): array
    {
        $totalConsumption = $consumption['total_consumption'] ?? 0;
        $daysAnalyzed = $consumption['days_analyzed'] ?? 1;

        $result = $this->calculator->calculateCost(
            $tariffId,
            $totalConsumption,
            new DateTimeImmutable()
        );

        // Get tariff details for context
        $tariff = $this->getTariff($tariffId);

        return [
            'tariff_id' => $tariffId,
            'tariff_name' => $tariff['name'] ?? 'Unknown',
            'tariff_code' => $tariff['code'] ?? null,
            'supplier_name' => $tariff['supplier_name'] ?? 'Unknown',
            'consumption' => $totalConsumption,
            'unit_cost' => $result->getUnitCost(),
            'standing_charge_cost' => $result->getStandingCharge() * $daysAnalyzed,
            'total_cost' => $result->getUnitCost() + ($result->getStandingCharge() * $daysAnalyzed),
            'breakdown' => $result->getBreakdown(),
        ];
    }

    /**
     * Get alternative tariffs for comparison (excluding current tariff).
     */
    private function getAlternativeTariffs(int $currentTariffId, ?string $energyType): array
    {
        try {
            $sql = '
                SELECT 
                    t.id,
                    t.name,
                    t.code,
                    t.energy_type,
                    t.tariff_type,
                    t.unit_rate,
                    t.standing_charge,
                    t.peak_rate,
                    t.off_peak_rate,
                    t.weekend_rate,
                    COALESCE(s.name, "Unknown") as supplier_name
                FROM tariffs t
                LEFT JOIN suppliers s ON t.supplier_id = s.id
                WHERE t.is_active = 1
                AND t.id != :current_tariff_id
                AND (t.valid_to IS NULL OR t.valid_to >= CURDATE())
            ';

            if ($energyType) {
                $sql .= ' AND t.energy_type = :energy_type';
            }

            $sql .= ' ORDER BY t.name ASC';

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':current_tariff_id', $currentTariffId, PDO::PARAM_INT);
            
            if ($energyType) {
                $stmt->bindValue(':energy_type', $energyType, PDO::PARAM_STR);
            }

            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('Failed to get alternative tariffs: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Find the best recommendation from alternatives.
     */
    private function findBestRecommendation(array $alternatives, array $currentCost): ?array
    {
        if (empty($alternatives)) {
            return null;
        }

        // The first alternative has the highest savings (already sorted)
        $best = $alternatives[0];

        // Only recommend if there are actual savings
        if ($best['potential_savings'] <= 0) {
            return null;
        }

        return $best;
    }

    /**
     * Get meter details.
     */
    private function getMeter(int $meterId): ?array
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT m.*, s.name as site_name, sup.name as supplier_name
                FROM meters m
                LEFT JOIN sites s ON m.site_id = s.id
                LEFT JOIN suppliers sup ON m.supplier_id = sup.id
                WHERE m.id = :id
            ');
            $stmt->execute(['id' => $meterId]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log('Failed to get meter: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get tariff details.
     */
    private function getTariff(int $tariffId): ?array
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT t.*, s.name as supplier_name
                FROM tariffs t
                LEFT JOIN suppliers s ON t.supplier_id = s.id
                WHERE t.id = :id
            ');
            $stmt->execute(['id' => $tariffId]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log('Failed to get tariff: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get current tariff for a meter (simplified - would need enhancement).
     */
    private function getCurrentTariffForMeter(int $meterId): ?int
    {
        try {
            // This is a simplified approach - in production, you might have 
            // a meter_tariffs junction table or tariff assignments
            $stmt = $this->pdo->prepare('
                SELECT t.id
                FROM meters m
                JOIN suppliers s ON m.supplier_id = s.id
                JOIN tariffs t ON t.supplier_id = s.id
                WHERE m.id = :meter_id
                AND t.is_active = 1
                AND t.energy_type = m.meter_type
                AND (t.valid_to IS NULL OR t.valid_to >= CURDATE())
                ORDER BY t.valid_from DESC
                LIMIT 1
            ');
            
            $stmt->execute(['meter_id' => $meterId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? (int) $result['id'] : null;
        } catch (PDOException $e) {
            error_log('Failed to get current tariff for meter: ' . $e->getMessage());
            return null;
        }
    }
}
