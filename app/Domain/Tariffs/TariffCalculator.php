<?php
/**
 * eclectyc-energy/app/Domain/Tariffs/TariffCalculator.php
 * Calculates energy costs based on tariff structures, including time-of-use rates.
 * Last updated: 06/11/2025
 */

namespace App\Domain\Tariffs;

use PDO;
use PDOException;
use DateTimeImmutable;

class TariffCalculator
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Calculate cost for a given consumption and tariff.
     *
     * @param int $tariffId Tariff identifier
     * @param float $consumption Consumption in kWh
     * @param DateTimeImmutable|null $date Date for time-based tariffs
     * @param string|null $timeBand Optional time band ('peak', 'off_peak', etc.)
     * @return TariffCalculationResult Calculation result
     */
    public function calculateCost(int $tariffId, float $consumption, ?DateTimeImmutable $date = null, ?string $timeBand = null): TariffCalculationResult
    {
        $tariff = $this->getTariff($tariffId);

        if (!$tariff) {
            return new TariffCalculationResult(0.0, 0.0, 0.0, []);
        }

        $rate = $this->resolveRate($tariff, $date, $timeBand);

        $unitRate = (float) ($rate['unit_rate'] ?? 0.0);
        $standingCharge = (float) ($rate['standing_charge'] ?? 0.0);
        $unitCost = $consumption * $unitRate;
        $totalCost = $unitCost + $standingCharge;

        $breakdown = [
            'unit_rate' => $unitRate,
            'standing_charge' => $standingCharge,
        ];

        if (isset($rate['time_band'])) {
            $breakdown['time_band'] = $rate['time_band'];
        }

        return new TariffCalculationResult($consumption, $unitCost, $totalCost, $breakdown);
    }

    /**
     * Get the applicable tariff rate for a specific date and time.
     *
     * @param int $tariffId Tariff identifier
     * @param DateTimeImmutable $dateTime Date and time for rate lookup
     * @return array{unit_rate: float, time_band: string, standing_charge: float} Rate details
     */
    public function getRateForDateTime(int $tariffId, DateTimeImmutable $dateTime): array
    {
        $tariff = $this->getTariff($tariffId);

        if (!$tariff) {
            return [
                'unit_rate' => 0.0,
                'time_band' => 'standard',
                'standing_charge' => 0.0,
            ];
        }

        return $this->resolveRate($tariff, $dateTime, null);
    }

    /**
     * Compare costs across multiple tariffs.
     *
     * @param array<int> $tariffIds Array of tariff IDs to compare
     * @param float $consumption Consumption in kWh
     * @param DateTimeImmutable|null $date Reference date for time-based rates
     * @return array{results: array<int, TariffCalculationResult>, recommended_tariff_id: int|null}
     */
    public function compareTariffs(array $tariffIds, float $consumption, ?DateTimeImmutable $date = null): array
    {
        $results = [];

        foreach ($tariffIds as $tariffId) {
            $results[$tariffId] = $this->calculateCost($tariffId, $consumption, $date);
        }

        return [
            'results' => $results,
            'recommended_tariff_id' => $this->findLowestCostTariff($results),
        ];
    }

    /**
     * Update tariff rates from supplier data.
     *
     * @param int $tariffId Tariff identifier
     * @param array<string, mixed> $rateData New rate data from supplier
     * @return bool True if updated successfully
     */
    public function updateTariffRates(int $tariffId, array $rateData): bool
    {
        $allowedKeys = [
            'unit_rate',
            'standing_charge',
            'peak_rate',
            'off_peak_rate',
            'weekend_rate',
            'valid_from',
            'valid_to',
            'tariff_type',
        ];

        $fields = array_intersect_key($rateData, array_flip($allowedKeys));

        if (empty($fields)) {
            return false;
        }

        $setClauses = [];
        foreach ($fields as $column => $_) {
            $setClauses[] = sprintf('%s = :%s', $column, $column);
        }

        $sql = 'UPDATE tariffs SET ' . implode(', ', $setClauses) . ', updated_at = NOW() WHERE id = :id';

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->bindValue(':id', $tariffId, PDO::PARAM_INT);

            foreach ($fields as $column => $value) {
                $statement->bindValue(':' . $column, $value);
            }

            return $statement->execute();
        } catch (PDOException $exception) {
            return false;
        }
    }

    /**
     * Retrieve tariff details from the database.
     */
    private function getTariff(int $tariffId): ?array
    {
        try {
            $statement = $this->pdo->prepare('SELECT * FROM tariffs WHERE id = :id LIMIT 1');
            $statement->bindValue(':id', $tariffId, PDO::PARAM_INT);
            $statement->execute();

            $result = $statement->fetch(PDO::FETCH_ASSOC);

            return $result ?: null;
        } catch (PDOException $exception) {
            return null;
        }
    }

    /**
     * Determine the most appropriate rate for a tariff given optional date/time hints.
     *
     * @param array<string, mixed> $tariff Tariff data
     * @param DateTimeImmutable|null $dateTime Optional date context
     * @param string|null $timeBand Optional explicit time band
     * @return array{unit_rate: float, standing_charge: float, time_band?: string}
     */
    private function resolveRate(array $tariff, ?DateTimeImmutable $dateTime, ?string $timeBand): array
    {
        $bandFromDate = null;

        if ($timeBand) {
            $bandRate = $this->getRateForTimeBand($tariff, $timeBand);
            if ($bandRate) {
                return $bandRate;
            }
        }

        if ($dateTime) {
            $bandFromDate = $this->determineTimeBand($dateTime);
            $bandRate = $this->getRateForTimeBand($tariff, $bandFromDate);
            if ($bandRate) {
                return $bandRate;
            }
        }

        $effectiveBand = $timeBand ?? $bandFromDate ?? 'standard';

        return [
            'unit_rate' => (float) ($tariff['unit_rate'] ?? 0.0),
            'standing_charge' => (float) ($tariff['standing_charge'] ?? 0.0),
            'time_band' => $effectiveBand,
        ];
    }

    /**
     * Return the best available rate for a named time band, if present on the tariff.
     *
     * @param array<string, mixed> $tariff Tariff data
     * @param string $timeBand Named time band
     * @return array{unit_rate: float, standing_charge: float, time_band: string}|null
     */
    private function getRateForTimeBand(array $tariff, string $timeBand): ?array
    {
        $column = match ($timeBand) {
            'peak' => 'peak_rate',
            'off_peak' => 'off_peak_rate',
            'weekend' => 'weekend_rate',
            default => 'unit_rate',
        };

        if (!isset($tariff[$column]) || $tariff[$column] === null) {
            return null;
        }

        return [
            'unit_rate' => (float) $tariff[$column],
            'standing_charge' => (float) ($tariff['standing_charge'] ?? 0.0),
            'time_band' => $timeBand,
        ];
    }

    /**
     * Derive a representative time band based on the provided date/time.
     */
    private function determineTimeBand(DateTimeImmutable $dateTime): string
    {
        $dayOfWeek = (int) $dateTime->format('N');
        if ($dayOfWeek >= 6) {
            return 'weekend';
        }

        $hour = (int) $dateTime->format('H');
        if ($hour >= 16 && $hour < 21) {
            return 'peak';
        }

        return 'off_peak';
    }

    /**
     * Identify the tariff with the lowest total cost.
     *
     * @param array<int, TariffCalculationResult> $results
     */
    private function findLowestCostTariff(array $results): ?int
    {
        $bestId = null;
        $bestCost = null;

        foreach ($results as $tariffId => $result) {
            if (!$result instanceof TariffCalculationResult) {
                continue;
            }

            $totalCost = $result->getTotalCost();

            if ($bestCost === null || $totalCost < $bestCost) {
                $bestCost = $totalCost;
                $bestId = $tariffId;
            }
        }

        return $bestId;
    }
}
