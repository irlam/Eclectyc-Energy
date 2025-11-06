<?php
/**
 * eclectyc-energy/app/Domain/Aggregation/PeriodAggregator.php
 * Rolls daily aggregations up into weekly, monthly, or annual summaries.
 */

namespace App\Domain\Aggregation;

use DateTimeImmutable;
use PDO;
use PDOException;

class PeriodAggregator
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function aggregate(string $period, DateTimeImmutable $startDate, DateTimeImmutable $endDate): PeriodAggregationSummary
    {
        $meta = $this->resolveMeta($period);
        $summary = new PeriodAggregationSummary($period, $startDate, $endDate);

        $meters = $this->fetchMeters();

        $statStmt = $this->pdo->prepare('
            SELECT
                COUNT(*) AS day_count,
                SUM(total_consumption) AS total_consumption,
                SUM(peak_consumption) AS peak_consumption,
                SUM(off_peak_consumption) AS off_peak_consumption,
                MIN(total_consumption) AS min_daily_consumption,
                MAX(total_consumption) AS max_daily_consumption,
                SUM(reading_count) AS reading_count
            FROM daily_aggregations
            WHERE meter_id = :meter_id
              AND date BETWEEN :start AND :end
        ');

        $insertSql = sprintf('
            INSERT INTO %s
                (meter_id, %s, %s, total_consumption, peak_consumption, off_peak_consumption,
                 min_daily_consumption, max_daily_consumption, day_count, reading_count)
            VALUES (:meter_id, :start, :end, :total_consumption, :peak_consumption, :off_peak_consumption,
                    :min_daily_consumption, :max_daily_consumption, :day_count, :reading_count)
            ON DUPLICATE KEY UPDATE
                total_consumption = VALUES(total_consumption),
                peak_consumption = VALUES(peak_consumption),
                off_peak_consumption = VALUES(off_peak_consumption),
                min_daily_consumption = VALUES(min_daily_consumption),
                max_daily_consumption = VALUES(max_daily_consumption),
                day_count = VALUES(day_count),
                reading_count = VALUES(reading_count),
                updated_at = CURRENT_TIMESTAMP
        ', $meta['table'], $meta['start_column'], $meta['end_column']);

        $insertStmt = $this->pdo->prepare($insertSql);

        foreach ($meters as $meter) {
            $summary->incrementTotalMeters();

            try {
                $statStmt->execute([
                    'meter_id' => $meter['id'],
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                ]);

                $stats = $statStmt->fetch() ?: [];
                $dayCount = (int) ($stats['day_count'] ?? 0);

                if ($dayCount === 0) {
                    $summary->incrementMetersWithoutData();
                    continue;
                }

                $summary->incrementMetersWithData();

                $insertStmt->execute([
                    'meter_id' => $meter['id'],
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                    'total_consumption' => (float) ($stats['total_consumption'] ?? 0.0),
                    'peak_consumption' => (float) ($stats['peak_consumption'] ?? 0.0),
                    'off_peak_consumption' => (float) ($stats['off_peak_consumption'] ?? 0.0),
                    'min_daily_consumption' => $stats['min_daily_consumption'] ?? null,
                    'max_daily_consumption' => $stats['max_daily_consumption'] ?? null,
                    'day_count' => $dayCount,
                    'reading_count' => (int) ($stats['reading_count'] ?? 0),
                ]);
            } catch (PDOException $exception) {
                $summary->registerError(sprintf(
                    '%s aggregation failed for meter %s: %s',
                    ucfirst($period),
                    $meter['mpan'] ?? $meter['id'],
                    $exception->getMessage()
                ));
            }
        }

        return $summary;
    }

    private function resolveMeta(string $period): array
    {
        return match ($period) {
            'weekly' => [
                'table' => 'weekly_aggregations',
                'start_column' => 'week_start',
                'end_column' => 'week_end',
            ],
            'monthly' => [
                'table' => 'monthly_aggregations',
                'start_column' => 'month_start',
                'end_column' => 'month_end',
            ],
            'annual' => [
                'table' => 'annual_aggregations',
                'start_column' => 'year_start',
                'end_column' => 'year_end',
            ],
            default => throw new \InvalidArgumentException('Unsupported aggregation period: ' . $period),
        };
    }

    private function fetchMeters(): array
    {
        $stmt = $this->pdo->query('SELECT id, mpan FROM meters WHERE is_active = TRUE');
        return $stmt->fetchAll() ?: [];
    }
}
