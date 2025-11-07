<?php
/**
 * eclectyc-energy/app/Domain/Comparison/ComparisonSnapshotService.php
 * Provides comparison snapshots (prev day/week/month/year) for analytics.
 */

namespace App\Domain\Comparison;

use PDO;
use DateTimeImmutable;

class ComparisonSnapshotService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get daily comparison snapshot.
     * 
     * @param int $meterId Meter identifier
     * @param DateTimeImmutable $date Current date
     * @return DailyComparisonSnapshot Comparison data
     */
    public function getDailyComparison(int $meterId, DateTimeImmutable $date): DailyComparisonSnapshot
    {
        $current = $this->fetchDailyData($meterId, $date);
        $prevDay = $this->fetchDailyData($meterId, $date->modify('-1 day'));
        $prevWeek = $this->fetchDailyData($meterId, $date->modify('-7 days'));
        $prevMonth = $this->fetchDailyData($meterId, $date->modify('-1 month'));
        $prevYear = $this->fetchDailyData($meterId, $date->modify('-1 year'));
        
        return new DailyComparisonSnapshot(
            current: $current,
            previousDay: $prevDay,
            previousWeek: $prevWeek,
            previousMonth: $prevMonth,
            previousYear: $prevYear
        );
    }

    /**
     * Get weekly comparison snapshot.
     */
    public function getWeeklyComparison(int $meterId, DateTimeImmutable $weekStart): WeeklyComparisonSnapshot
    {
        $weekEnd = $weekStart->modify('+6 days');
        
        $current = $this->fetchWeeklyData($meterId, $weekStart, $weekEnd);
        $prevWeek = $this->fetchWeeklyData($meterId, $weekStart->modify('-7 days'), $weekEnd->modify('-7 days'));
        $prevMonth = $this->fetchWeeklyData($meterId, $weekStart->modify('-1 month'), $weekEnd->modify('-1 month'));
        $prevYear = $this->fetchWeeklyData($meterId, $weekStart->modify('-1 year'), $weekEnd->modify('-1 year'));
        
        return new WeeklyComparisonSnapshot(
            current: $current,
            previousWeek: $prevWeek,
            previousMonth: $prevMonth,
            previousYear: $prevYear
        );
    }

    /**
     * Get monthly comparison snapshot.
     */
    public function getMonthlyComparison(int $meterId, DateTimeImmutable $monthStart): MonthlyComparisonSnapshot
    {
        $monthEnd = $monthStart->modify('last day of this month');
        
        $current = $this->fetchMonthlyData($meterId, $monthStart, $monthEnd);
        $prevMonth = $this->fetchMonthlyData($meterId, $monthStart->modify('-1 month'), $monthEnd->modify('-1 month'));
        $prevYear = $this->fetchMonthlyData($meterId, $monthStart->modify('-1 year'), $monthEnd->modify('-1 year'));
        
        return new MonthlyComparisonSnapshot(
            current: $current,
            previousMonth: $prevMonth,
            previousYear: $prevYear
        );
    }

    /**
     * Get annual comparison snapshot.
     */
    public function getAnnualComparison(int $meterId, DateTimeImmutable $yearStart): AnnualComparisonSnapshot
    {
        $yearEnd = $yearStart->setDate((int)$yearStart->format('Y'), 12, 31);
        
        $current = $this->fetchAnnualData($meterId, $yearStart, $yearEnd);
        $prevYear = $this->fetchAnnualData($meterId, $yearStart->modify('-1 year'), $yearEnd->modify('-1 year'));
        
        return new AnnualComparisonSnapshot(
            current: $current,
            previousYear: $prevYear
        );
    }

    /**
     * Calculate percentage change between current and previous period.
     */
    public function calculateChange(?float $current, ?float $previous): array
    {
        if ($previous === null || $previous == 0) {
            return [
                'absolute' => $current,
                'percentage' => null,
                'trend' => 'unknown',
            ];
        }
        
        $absolute = $current - $previous;
        $percentage = ($absolute / $previous) * 100;
        
        return [
            'absolute' => $absolute,
            'percentage' => $percentage,
            'trend' => $absolute > 0 ? 'increase' : ($absolute < 0 ? 'decrease' : 'stable'),
        ];
    }

    private function fetchDailyData(int $meterId, DateTimeImmutable $date): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                date,
                total_consumption,
                peak_consumption,
                off_peak_consumption,
                min_reading,
                max_reading,
                reading_count
            FROM daily_aggregations
            WHERE meter_id = :meter_id AND date = :date
        ');
        
        $stmt->execute([
            'meter_id' => $meterId,
            'date' => $date->format('Y-m-d'),
        ]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    private function fetchWeeklyData(int $meterId, DateTimeImmutable $start, DateTimeImmutable $end): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                week_start,
                week_end,
                total_consumption,
                peak_consumption,
                off_peak_consumption,
                min_daily_consumption,
                max_daily_consumption,
                day_count,
                reading_count
            FROM weekly_aggregations
            WHERE meter_id = :meter_id AND week_start = :start
        ');
        
        $stmt->execute([
            'meter_id' => $meterId,
            'start' => $start->format('Y-m-d'),
        ]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    private function fetchMonthlyData(int $meterId, DateTimeImmutable $start, DateTimeImmutable $end): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                month_start,
                month_end,
                total_consumption,
                peak_consumption,
                off_peak_consumption,
                min_daily_consumption,
                max_daily_consumption,
                day_count,
                reading_count
            FROM monthly_aggregations
            WHERE meter_id = :meter_id AND month_start = :start
        ');
        
        $stmt->execute([
            'meter_id' => $meterId,
            'start' => $start->format('Y-m-d'),
        ]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    private function fetchAnnualData(int $meterId, DateTimeImmutable $start, DateTimeImmutable $end): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                year_start,
                year_end,
                total_consumption,
                peak_consumption,
                off_peak_consumption,
                min_daily_consumption,
                max_daily_consumption,
                day_count,
                reading_count
            FROM annual_aggregations
            WHERE meter_id = :meter_id AND year_start = :start
        ');
        
        $stmt->execute([
            'meter_id' => $meterId,
            'start' => $start->format('Y-m-d'),
        ]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
