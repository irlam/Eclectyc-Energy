<?php
/**
 * eclectyc-energy/app/Domain/Comparison/WeeklyComparisonSnapshot.php
 * Value object for weekly comparison data.
 */

namespace App\Domain\Comparison;

class WeeklyComparisonSnapshot
{
    public function __construct(
        private ?array $current,
        private ?array $previousWeek,
        private ?array $previousMonth,
        private ?array $previousYear
    ) {}

    public function getCurrent(): ?array
    {
        return $this->current;
    }

    public function toArray(): array
    {
        return [
            'current' => $this->current,
            'previous_week' => $this->previousWeek,
            'previous_month' => $this->previousMonth,
            'previous_year' => $this->previousYear,
        ];
    }
}
