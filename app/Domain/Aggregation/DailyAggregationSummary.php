<?php
/**
 * eclectyc-energy/app/Domain/Aggregation/DailyAggregationSummary.php
 * Value object capturing the outcome of a daily aggregation run.
 */

namespace App\Domain\Aggregation;

use DateTimeImmutable;

class DailyAggregationSummary
{
    private DateTimeImmutable $date;
    private int $totalMeters = 0;
    private int $metersWithReadings = 0;
    private int $metersWithoutReadings = 0;
    private int $errors = 0;
    /**
     * @var array<int, string>
     */
    private array $errorMessages = [];

    public function __construct(DateTimeImmutable $date)
    {
        $this->date = $date;
    }

    public function getDate(): DateTimeImmutable
    {
        return $this->date;
    }

    public function incrementTotalMeters(): void
    {
        $this->totalMeters++;
    }

    public function incrementMetersWithReadings(): void
    {
        $this->metersWithReadings++;
    }

    public function incrementMetersWithoutReadings(): void
    {
        $this->metersWithoutReadings++;
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

    public function getMetersWithReadings(): int
    {
        return $this->metersWithReadings;
    }

    public function getMetersWithData(): int
    {
        return $this->metersWithReadings;
    }

    public function getMetersWithoutReadings(): int
    {
        return $this->metersWithoutReadings;
    }

    public function getMetersWithoutData(): int
    {
        return $this->metersWithoutReadings;
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
            'date' => $this->date->format('Y-m-d'),
            'total_meters' => $this->totalMeters,
            'meters_with_readings' => $this->metersWithReadings,
            'meters_with_data' => $this->metersWithReadings,
            'meters_without_readings' => $this->metersWithoutReadings,
            'meters_without_data' => $this->metersWithoutReadings,
            'errors' => $this->errors,
            'error_messages' => $this->errorMessages,
        ];
    }
}
