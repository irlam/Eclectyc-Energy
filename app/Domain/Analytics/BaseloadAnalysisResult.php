<?php
/**
 * eclectyc-energy/app/Domain/Analytics/BaseloadAnalysisResult.php
 * Summary of baseload analysis results.
 */

namespace App\Domain\Analytics;

use DateTimeImmutable;

class BaseloadAnalysisResult
{
    private int $meterId;
    private DateTimeImmutable $startDate;
    private DateTimeImmutable $endDate;
    private float $baseload = 0.0;
    private float $averageConsumption = 0.0;
    private float $baseloadPercentage = 0.0;
    private int $dataPointCount = 0;
    private array $errors = [];

    public function __construct(int $meterId, DateTimeImmutable $startDate, DateTimeImmutable $endDate)
    {
        $this->meterId = $meterId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function setBaseload(float $baseload): void
    {
        $this->baseload = $baseload;
    }

    public function setAverageConsumption(float $average): void
    {
        $this->averageConsumption = $average;
    }

    public function setBaseloadPercentage(float $percentage): void
    {
        $this->baseloadPercentage = $percentage;
    }

    public function setDataPointCount(int $count): void
    {
        $this->dataPointCount = $count;
    }

    public function registerError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function getBaseload(): float
    {
        return $this->baseload;
    }

    public function getAverageConsumption(): float
    {
        return $this->averageConsumption;
    }

    public function getBaseloadPercentage(): float
    {
        return $this->baseloadPercentage;
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
            'baseload' => $this->baseload,
            'average_consumption' => $this->averageConsumption,
            'baseload_percentage' => $this->baseloadPercentage,
            'data_points' => $this->dataPointCount,
            'errors' => $this->errors,
        ];
    }
}
