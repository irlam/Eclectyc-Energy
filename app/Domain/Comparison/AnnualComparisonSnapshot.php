<?php
/**
 * eclectyc-energy/app/Domain/Comparison/AnnualComparisonSnapshot.php
 * Value object for annual comparison data.
 */

namespace App\Domain\Comparison;

class AnnualComparisonSnapshot
{
    public function __construct(
        private ?array $current,
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
            'previous_year' => $this->previousYear,
        ];
    }
}
