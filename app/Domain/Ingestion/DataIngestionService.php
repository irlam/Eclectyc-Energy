<?php
/**
 * eclectyc-energy/app/Domain/Ingestion/DataIngestionService.php
 * Handles ingestion of meter reading data from various sources and formats.
 */

namespace App\Domain\Ingestion;

use PDO;
use PDOException;

class DataIngestionService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Ingest meter readings from a data source
     * 
     * @param array $readings Array of reading data
     * @return IngestionResult Result summary with counts and errors
     */
    public function ingest(array $readings): IngestionResult
    {
        $result = new IngestionResult();

        foreach ($readings as $reading) {
            try {
                $this->validateReading($reading);
                $this->storeReading($reading);
                $result->incrementSuccessCount();
            } catch (\Exception $e) {
                $result->registerError($e->getMessage());
            }
        }

        return $result;
    }

    /**
     * Validate a reading record
     * 
     * @param array $reading Reading data to validate
     * @throws \InvalidArgumentException if validation fails
     */
    private function validateReading(array $reading): void
    {
        if (!isset($reading['meter_id'])) {
            throw new \InvalidArgumentException('Missing meter_id');
        }

        if (!isset($reading['reading_date'])) {
            throw new \InvalidArgumentException('Missing reading_date');
        }

        if (!isset($reading['reading_value'])) {
            throw new \InvalidArgumentException('Missing reading_value');
        }
    }

    /**
     * Store a reading in the database
     * 
     * @param array $reading Reading data to store
     * @throws PDOException if database operation fails
     */
    private function storeReading(array $reading): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO meter_readings 
                (meter_id, reading_date, reading_time, reading_value, reading_type)
            VALUES (:meter_id, :reading_date, :reading_time, :reading_value, :reading_type)
            ON DUPLICATE KEY UPDATE
                reading_value = VALUES(reading_value),
                updated_at = CURRENT_TIMESTAMP
        ');

        $stmt->execute([
            'meter_id' => $reading['meter_id'],
            'reading_date' => $reading['reading_date'],
            'reading_time' => $reading['reading_time'] ?? null,
            'reading_value' => $reading['reading_value'],
            'reading_type' => $reading['reading_type'] ?? 'import',
        ]);
    }
}
