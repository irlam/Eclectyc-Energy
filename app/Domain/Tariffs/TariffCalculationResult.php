<?php
/**
 * eclectyc-energy/app/Domain/Tariffs/TariffCalculationResult.php
 * Value object representing the result of a tariff calculation.
 * Last updated: 06/11/2025
 */

namespace App\Domain\Tariffs;

class TariffCalculationResult
{
    private float $consumption;
    private float $unitCost;
    private float $totalCost;
    /**
     * @var array<string, mixed>
     */
    private array $breakdown;

    /**
     * @param float $consumption Consumption in kWh
     * @param float $unitCost Cost from unit rates
     * @param float $totalCost Total cost including standing charges
     * @param array<string, mixed> $breakdown Detailed cost breakdown
     */
    public function __construct(float $consumption, float $unitCost, float $totalCost, array $breakdown = [])
    {
        $this->consumption = $consumption;
        $this->unitCost = $unitCost;
        $this->totalCost = $totalCost;
        $this->breakdown = $breakdown;
    }

    public function getConsumption(): float
    {
        return $this->consumption;
    }

    public function getUnitCost(): float
    {
        return $this->unitCost;
    }

    public function getTotalCost(): float
    {
        return $this->totalCost;
    }

    public function getStandingCharge(): float
    {
        return $this->totalCost - $this->unitCost;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBreakdown(): array
    {
        return $this->breakdown;
    }

    public function toArray(): array
    {
        return [
            'consumption' => $this->consumption,
            'unit_cost' => $this->unitCost,
            'standing_charge' => $this->getStandingCharge(),
            'total_cost' => $this->totalCost,
            'breakdown' => $this->breakdown,
        ];
    }
}
