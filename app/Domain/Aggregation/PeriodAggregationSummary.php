<?php
/**
 * eclectyc-energy/app/Domain/Aggregation/PeriodAggregationSummary.php
 * Summary DTO for weekly, monthly, and annual aggregations.
 */

namespace App\Domain\Aggregation;

use DateTimeImmutable;

class PeriodAggregationSummary
{
    private string $period;
    private DateTimeImmutable $startDate;
    private DateTimeImmutable $endDate;
    private int $totalMeters = 0;
    private int $metersWithData = 0;
    private int $metersWithoutData = 0;
    private int $errors = 0;
    /**
     * @var array<int, string>
     */
    private array $errorMessages = [];

    public function __construct(string $period, DateTimeImmutable $startDate, DateTimeImmutable $endDate)
    {
        $this->period = $period;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function getPeriod(): string
    {
        return $this->period;
    }

    public function getStartDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    public function getEndDate(): DateTimeImmutable
    {
        return $this->endDate;
    }

    public function incrementTotalMeters(): void
    {
        $this->totalMeters++;
    }

    public function incrementMetersWithData(): void
    {
        $this->metersWithData++;
    }

    public function incrementMetersWithoutData(): void
    {
        $this->metersWithoutData++;
    }

    public function registerError(string $message): void
    {
        $this->errors++;
        $this->errorMessages[] = $message;
    }

    public function getTotalMeters(): int
    {
        return $this->totalMeters;
    }

    public function getMetersWithData(): int
    {
        return $this->metersWithData;
    }

    public function getMetersWithoutData(): int
    {
        return $this->metersWithoutData;
    }

    public function getErrors(): int
    {
        return $this->errors;
    }

    /**
     * @return array<int, string>
     */
    public function getErrorMessages(): array
    {
        return $this->errorMessages;
    }

    public function toArray(): array
    {
        return [
            'period' => $this->period,
            'period_start' => $this->startDate->format('Y-m-d'),
            'period_end' => $this->endDate->format('Y-m-d'),
            'total_meters' => $this->totalMeters,
            'meters_with_data' => $this->metersWithData,
            'meters_without_data' => $this->metersWithoutData,
            'errors' => $this->errors,
            'error_messages' => $this->errorMessages,
        ];
    }
}
