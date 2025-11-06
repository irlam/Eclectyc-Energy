<?php
/**
 * eclectyc-energy/app/Domain/Tariffs/TariffCalculationResult.php
 * Summary of tariff cost calculation results.
 */

namespace App\Domain\Tariffs;

use DateTimeImmutable;

class TariffCalculationResult
{
    private int $meterId;
    private DateTimeImmutable $startDate;
    private DateTimeImmutable $endDate;
    private float $totalCost = 0.0;
    private float $peakCost = 0.0;
    private float $offPeakCost = 0.0;
    private array $dailyCosts = [];
    private array $errors = [];

    public function __construct(int $meterId, DateTimeImmutable $startDate, DateTimeImmutable $endDate)
    {
        $this->meterId = $meterId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function addDailyCost(string $date, float $total, float $peak, float $offPeak): void
    {
        $this->dailyCosts[$date] = [
            'total' => $total,
            'peak' => $peak,
            'off_peak' => $offPeak,
        ];
        $this->totalCost += $total;
        $this->peakCost += $peak;
        $this->offPeakCost += $offPeak;
    }

    public function registerError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function getTotalCost(): float
    {
        return $this->totalCost;
    }

    public function getPeakCost(): float
    {
        return $this->peakCost;
    }

    public function getOffPeakCost(): float
    {
        return $this->offPeakCost;
    }

    public function getDailyCosts(): array
    {
        return $this->dailyCosts;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function toArray(): array
    {
        return [
            'meter_id' => $this->meterId,
            'start_date' => $this->startDate->format('Y-m-d'),
            'end_date' => $this->endDate->format('Y-m-d'),
            'total_cost' => $this->totalCost,
            'peak_cost' => $this->peakCost,
            'off_peak_cost' => $this->offPeakCost,
            'daily_costs' => $this->dailyCosts,
            'errors' => $this->errors,
        ];
    }
}
