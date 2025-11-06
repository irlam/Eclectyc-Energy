<?php
/**
 * eclectyc-energy/app/Domain/Aggregation/DailyAggregator.php
 * Aggregates raw meter readings into the daily_aggregations table.
 */

namespace App\Domain\Aggregation;

use DateTimeImmutable;
use PDO;
use PDOException;

class DailyAggregator
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function aggregate(DateTimeImmutable $date): DailyAggregationSummary
    {
        $summary = new DailyAggregationSummary($date);
        $meters = $this->fetchMeters();

        $statStmt = $this->pdo->prepare('
            SELECT 
                COUNT(*) AS reading_count,
                SUM(reading_value) AS total_consumption,
                MIN(reading_value) AS min_reading,
                MAX(reading_value) AS max_reading
            FROM meter_readings
            WHERE meter_id = :meter_id AND reading_date = :reading_date
        ');

        $peakStmt = $this->pdo->prepare('
            SELECT SUM(reading_value) AS peak_consumption
            FROM meter_readings
            WHERE meter_id = :meter_id 
              AND reading_date = :reading_date
              AND (reading_time IS NULL OR (reading_time BETWEEN "07:00:00" AND "23:00:00"))
        ');

        $insertStmt = $this->pdo->prepare('
            INSERT INTO daily_aggregations 
                (meter_id, date, total_consumption, peak_consumption, off_peak_consumption, 
                 min_reading, max_reading, reading_count)
            VALUES (:meter_id, :date, :total_consumption, :peak_consumption, :off_peak_consumption, 
                    :min_reading, :max_reading, :reading_count)
            ON DUPLICATE KEY UPDATE
                total_consumption = VALUES(total_consumption),
                peak_consumption = VALUES(peak_consumption),
                off_peak_consumption = VALUES(off_peak_consumption),
                min_reading = VALUES(min_reading),
                max_reading = VALUES(max_reading),
                reading_count = VALUES(reading_count),
                updated_at = CURRENT_TIMESTAMP
        ');

        foreach ($meters as $meter) {
            $summary->incrementTotalMeters();

            try {
                $statStmt->execute([
                    'meter_id' => $meter['id'],
                    'reading_date' => $date->format('Y-m-d'),
                ]);
                $stats = $statStmt->fetch() ?: [];

                $readingCount = (int) ($stats['reading_count'] ?? 0);
                if ($readingCount === 0) {
                    $summary->incrementMetersWithoutReadings();
                    continue;
                }

                $summary->incrementMetersWithReadings();

                $peakStmt->execute([
                    'meter_id' => $meter['id'],
                    'reading_date' => $date->format('Y-m-d'),
                ]);
                $peakData = $peakStmt->fetch() ?: [];

                $totalConsumption = (float) ($stats['total_consumption'] ?? 0.0);
                $peakConsumption = (float) ($peakData['peak_consumption'] ?? 0.0);
                $offPeakConsumption = $totalConsumption - $peakConsumption;

                $insertStmt->execute([
                    'meter_id' => $meter['id'],
                    'date' => $date->format('Y-m-d'),
                    'total_consumption' => $totalConsumption,
                    'peak_consumption' => $peakConsumption,
                    'off_peak_consumption' => $offPeakConsumption,
                    'min_reading' => $stats['min_reading'] ?? null,
                    'max_reading' => $stats['max_reading'] ?? null,
                    'reading_count' => $readingCount,
                ]);
            } catch (PDOException $e) {
                $summary->registerError(sprintf(
                    'Meter %s failed: %s',
                    $meter['mpan'] ?? $meter['id'],
                    $e->getMessage()
                ));
            }
        }

        return $summary;
    }

    private function fetchMeters(): array
    {
        $stmt = $this->pdo->query('SELECT id, mpan FROM meters WHERE is_active = TRUE');
        return $stmt->fetchAll() ?: [];
    }
}
