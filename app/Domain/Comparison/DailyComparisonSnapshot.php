<?php
/**
 * eclectyc-energy/app/Domain/Comparison/DailyComparisonSnapshot.php
 * Value object for daily comparison data.
 */

namespace App\Domain\Comparison;

class DailyComparisonSnapshot
{
    public function __construct(
        private ?array $current,
        private ?array $previousDay,
        private ?array $previousWeek,
        private ?array $previousMonth,
        private ?array $previousYear
    ) {}

    public function getCurrent(): ?array
    {
        return $this->current;
    }

    public function getPreviousDay(): ?array
    {
        return $this->previousDay;
    }

    public function getPreviousWeek(): ?array
    {
        return $this->previousWeek;
    }

    public function getPreviousMonth(): ?array
    {
        return $this->previousMonth;
    }

    public function getPreviousYear(): ?array
    {
        return $this->previousYear;
    }

    public function getCurrentConsumption(): ?float
    {
        return $this->current['total_consumption'] ?? null;
    }

    public function getDayOverDayChange(): array
    {
        $current = $this->getCurrentConsumption();
        $previous = $this->previousDay['total_consumption'] ?? null;
        
        return $this->calculateChange($current, $previous);
    }

    public function getWeekOverWeekChange(): array
    {
        $current = $this->getCurrentConsumption();
        $previous = $this->previousWeek['total_consumption'] ?? null;
        
        return $this->calculateChange($current, $previous);
    }

    public function getMonthOverMonthChange(): array
    {
        $current = $this->getCurrentConsumption();
        $previous = $this->previousMonth['total_consumption'] ?? null;
        
        return $this->calculateChange($current, $previous);
    }

    public function getYearOverYearChange(): array
    {
        $current = $this->getCurrentConsumption();
        $previous = $this->previousYear['total_consumption'] ?? null;
        
        return $this->calculateChange($current, $previous);
    }

    private function calculateChange(?float $current, ?float $previous): array
    {
        if ($current === null) {
            return ['absolute' => null, 'percentage' => null, 'trend' => 'no_data'];
        }
        
        if ($previous === null || $previous == 0) {
            return ['absolute' => $current, 'percentage' => null, 'trend' => 'no_comparison'];
        }
        
        $absolute = $current - $previous;
        $percentage = ($absolute / $previous) * 100;
        
        return [
            'absolute' => $absolute,
            'percentage' => $percentage,
            'trend' => $absolute > 0 ? 'increase' : ($absolute < 0 ? 'decrease' : 'stable'),
        ];
    }

    public function toArray(): array
    {
        return [
            'current' => $this->current,
            'comparisons' => [
                'day_over_day' => $this->getDayOverDayChange(),
                'week_over_week' => $this->getWeekOverWeekChange(),
                'month_over_month' => $this->getMonthOverMonthChange(),
                'year_over_year' => $this->getYearOverYearChange(),
            ],
        ];
    }
}
