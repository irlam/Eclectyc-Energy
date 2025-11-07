<?php
/**
 * eclectyc-energy/app/Domain/External/ExternalDataService.php
 * Integrates external datasets (temperature, calorific values) for analytics.
 */

namespace App\Domain\External;

use PDO;
use DateTimeImmutable;

class ExternalDataService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Store temperature data for a location and date.
     */
    public function storeTemperatureData(string $location, DateTimeImmutable $date, array $data): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO external_temperature_data 
                (location, date, avg_temperature, min_temperature, max_temperature, source, created_at)
            VALUES (:location, :date, :avg_temp, :min_temp, :max_temp, :source, NOW())
            ON DUPLICATE KEY UPDATE
                avg_temperature = VALUES(avg_temperature),
                min_temperature = VALUES(min_temperature),
                max_temperature = VALUES(max_temperature),
                updated_at = NOW()
        ');
        
        $stmt->execute([
            'location' => $location,
            'date' => $date->format('Y-m-d'),
            'avg_temp' => $data['avg_temperature'] ?? null,
            'min_temp' => $data['min_temperature'] ?? null,
            'max_temp' => $data['max_temperature'] ?? null,
            'source' => $data['source'] ?? 'manual',
        ]);
    }

    /**
     * Get temperature data for a location and date range.
     */
    public function getTemperatureData(string $location, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                date,
                avg_temperature,
                min_temperature,
                max_temperature,
                source
            FROM external_temperature_data
            WHERE location = :location 
              AND date BETWEEN :start_date AND :end_date
            ORDER BY date
        ');
        
        $stmt->execute([
            'location' => $location,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ]);
        
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Store calorific value data for gas meters.
     */
    public function storeCalorificValues(string $region, DateTimeImmutable $date, array $data): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO external_calorific_values 
                (region, date, calorific_value, unit, source, created_at)
            VALUES (:region, :date, :calorific_value, :unit, :source, NOW())
            ON DUPLICATE KEY UPDATE
                calorific_value = VALUES(calorific_value),
                updated_at = NOW()
        ');
        
        $stmt->execute([
            'region' => $region,
            'date' => $date->format('Y-m-d'),
            'calorific_value' => $data['calorific_value'],
            'unit' => $data['unit'] ?? 'MJ/m3',
            'source' => $data['source'] ?? 'manual',
        ]);
    }

    /**
     * Get calorific values for a region and date range.
     */
    public function getCalorificValues(string $region, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                date,
                calorific_value,
                unit,
                source
            FROM external_calorific_values
            WHERE region = :region 
              AND date BETWEEN :start_date AND :end_date
            ORDER BY date
        ');
        
        $stmt->execute([
            'region' => $region,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ]);
        
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Store carbon intensity data for grid carbon reporting.
     */
    public function storeCarbonIntensity(string $region, DateTimeImmutable $datetime, array $data): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO external_carbon_intensity 
                (region, datetime, intensity, forecast, actual, source, created_at)
            VALUES (:region, :datetime, :intensity, :forecast, :actual, :source, NOW())
            ON DUPLICATE KEY UPDATE
                intensity = VALUES(intensity),
                forecast = VALUES(forecast),
                actual = VALUES(actual),
                updated_at = NOW()
        ');
        
        $stmt->execute([
            'region' => $region,
            'datetime' => $datetime->format('Y-m-d H:i:s'),
            'intensity' => $data['intensity'],
            'forecast' => $data['forecast'] ?? null,
            'actual' => $data['actual'] ?? null,
            'source' => $data['source'] ?? 'manual',
        ]);
    }

    /**
     * Get carbon intensity data for a region and time range.
     */
    public function getCarbonIntensity(string $region, DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                datetime,
                intensity,
                forecast,
                actual,
                source
            FROM external_carbon_intensity
            WHERE region = :region 
              AND datetime BETWEEN :start_date AND :end_date
            ORDER BY datetime
        ');
        
        $stmt->execute([
            'region' => $region,
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => $endDate->format('Y-m-d H:i:s'),
        ]);
        
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Calculate carbon emissions for a meter based on consumption and carbon intensity.
     */
    public function calculateCarbonEmissions(int $meterId, DateTimeImmutable $startDate, DateTimeImmutable $endDate, string $region): array
    {
        // Get consumption data
        $consumptionStmt = $this->pdo->prepare('
            SELECT 
                date,
                total_consumption
            FROM daily_aggregations
            WHERE meter_id = :meter_id 
              AND date BETWEEN :start_date AND :end_date
        ');
        
        $consumptionStmt->execute([
            'meter_id' => $meterId,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ]);
        
        $consumptionData = $consumptionStmt->fetchAll() ?: [];
        
        // Get carbon intensity data
        $carbonData = $this->getCarbonIntensity($region, $startDate, $endDate);
        
        // Map carbon intensity by date
        $carbonByDate = [];
        foreach ($carbonData as $entry) {
            $date = substr($entry['datetime'], 0, 10);
            if (!isset($carbonByDate[$date])) {
                $carbonByDate[$date] = [];
            }
            $carbonByDate[$date][] = $entry;
        }
        
        $totalEmissions = 0;
        $dailyEmissions = [];
        
        foreach ($consumptionData as $consumption) {
            $date = $consumption['date'];
            $kwh = (float) $consumption['total_consumption'];
            
            // Use average carbon intensity for the day if available
            $avgIntensity = 0;
            if (isset($carbonByDate[$date])) {
                $intensities = array_column($carbonByDate[$date], 'intensity');
                $avgIntensity = array_sum($intensities) / count($intensities);
            }
            
            // Calculate emissions (kWh * gCO2/kWh / 1000 = kgCO2)
            $emissions = ($kwh * $avgIntensity) / 1000;
            
            $dailyEmissions[] = [
                'date' => $date,
                'consumption_kwh' => $kwh,
                'carbon_intensity' => $avgIntensity,
                'emissions_kg_co2' => round($emissions, 3),
            ];
            
            $totalEmissions += $emissions;
        }
        
        return [
            'meter_id' => $meterId,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'total_emissions_kg_co2' => round($totalEmissions, 3),
            'total_emissions_tonnes_co2' => round($totalEmissions / 1000, 3),
            'daily_breakdown' => $dailyEmissions,
        ];
    }
}
