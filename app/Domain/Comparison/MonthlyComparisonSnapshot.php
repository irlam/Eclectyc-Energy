<?php
/**
 * eclectyc-energy/app/Domain/Comparison/MonthlyComparisonSnapshot.php
 * Value object for monthly comparison data.
 */

namespace App\Domain\Comparison;

class MonthlyComparisonSnapshot
{
    public function __construct(
        private ?array $current,
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
            'previous_month' => $this->previousMonth,
            'previous_year' => $this->previousYear,
        ];
    }
}
