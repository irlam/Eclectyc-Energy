<?php
/**
 * eclectyc-energy/app/Domain/Ingestion/CsvIngestionService.php
 * Handles ingestion of meter readings from CSV files and various data sources.
 * Part of the data ingestion pipeline for processing 1-minute to half-hourly data.
 */

namespace App\Domain\Ingestion;

use DateTime;
use DateTimeImmutable;
use Exception;
use League\Csv\Reader;
use PDO;
use PDOException;
use Ramsey\Uuid\Uuid;

class CsvIngestionService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Ingest meter readings from a CSV file.
     * 
     * @param string $filePath Path to the CSV file
     * @param string $format Format type: 'hh' (half-hourly), 'daily', or 'minute'
     * @param string|null $batchId Optional batch identifier for tracking imports
     * @return array Statistics about the ingestion process
     * @throws Exception If file cannot be processed
     */
    public function ingestFromCsv(string $filePath, string $format = 'hh', ?string $batchId = null, bool $dryRun = false, ?int $userId = null): IngestionResult
    {
        if (!is_readable($filePath)) {
            throw new Exception('CSV file cannot be read: ' . $filePath);
        }

        $format = strtolower($format);
        if (!in_array($format, ['hh', 'daily'], true)) {
            throw new Exception('Unsupported import format. Use "hh" or "daily".');
        }

        $batchId = $batchId ?: Uuid::uuid4()->toString();

        $reader = Reader::createFromPath($filePath);
        $reader->setHeaderOffset(0);

        $headers = array_map('strtolower', $reader->getHeader());
        if (!in_array('mpan', $headers, true) || !in_array('date', $headers, true)) {
            throw new Exception('CSV must include "MPAN" and "Date" columns.');
        }

        $meterStmt = $this->pdo->prepare('SELECT id FROM meters WHERE mpan = :mpan LIMIT 1');
        $insertStmt = $this->pdo->prepare('
            INSERT INTO meter_readings
                (meter_id, reading_date, reading_time, period_number, reading_value, reading_type, import_batch_id)
            VALUES (:meter_id, :reading_date, :reading_time, :period_number, :reading_value, :reading_type, :batch_id)
            ON DUPLICATE KEY UPDATE
                reading_value = VALUES(reading_value),
                updated_at = CURRENT_TIMESTAMP
        ');

        $processed = 0;
        $successfulRows = 0;
        $totalValues = 0;
        $errors = [];

        foreach ($reader->getRecords() as $rowNumber => $record) {
            $processed++;

            try {
                $mpan = $this->normaliseString($record['MPAN'] ?? $record['mpan'] ?? '');
                if ($mpan === '') {
                    throw new Exception('Missing MPAN');
                }

                $meterStmt->execute(['mpan' => $mpan]);
                $meter = $meterStmt->fetch();
                if (!$meter) {
                    throw new Exception('Meter not found for MPAN ' . $mpan);
                }
                $meterId = (int) $meter['id'];

                $dateValue = $this->parseDate($record['Date'] ?? $record['date'] ?? null);
                if (!$dateValue) {
                    throw new Exception('Invalid date');
                }

                $rowValues = 0;

                if ($format === 'hh') {
                    for ($period = 1; $period <= 48; $period++) {
                        $column = sprintf('HH%02d', $period);
                        $raw = $record[$column] ?? $record[strtolower($column)] ?? null;

                        if ($raw === null || $this->normaliseString((string) $raw) === '') {
                            continue;
                        }

                        if (!is_numeric($raw)) {
                            throw new Exception(sprintf('Non-numeric value "%s" in %s', (string) $raw, $column));
                        }

                        $value = (float) $raw;
                        $time = $this->periodToTime($period);

                        if (!$dryRun) {
                            $insertStmt->execute([
                                'meter_id' => $meterId,
                                'reading_date' => $dateValue->format('Y-m-d'),
                                'reading_time' => $time,
                                'period_number' => $period,
                                'reading_value' => $value,
                                'reading_type' => 'actual',
                                'batch_id' => $batchId,
                            ]);
                        }

                        $rowValues++;
                        $totalValues++;
                    }
                } else {
                    $raw = $record['Reading'] ?? $record['reading'] ?? null;
                    if ($raw === null || $this->normaliseString((string) $raw) === '') {
                        throw new Exception('Missing reading value');
                    }

                    if (!is_numeric($raw)) {
                        throw new Exception('Non-numeric reading value "' . (string) $raw . '"');
                    }

                    $value = (float) $raw;

                    if (!$dryRun) {
                        $insertStmt->execute([
                            'meter_id' => $meterId,
                            'reading_date' => $dateValue->format('Y-m-d'),
                            'reading_time' => '00:00:00',
                            'period_number' => null,
                            'reading_value' => $value,
                            'reading_type' => 'actual',
                            'batch_id' => $batchId,
                        ]);
                    }

                    $rowValues++;
                    $totalValues++;
                }

                if ($rowValues > 0) {
                    $successfulRows++;
                }
            } catch (PDOException $pdoException) {
                $this->addError($errors, $processed, $pdoException->getMessage());
            } catch (Exception $exception) {
                $this->addError($errors, $processed, $exception->getMessage());
            }
        }

        $result = new IngestionResult($processed, $successfulRows, $errors, $batchId, $dryRun, [
            'format' => $format,
            'total_values_processed' => $totalValues,
        ]);

        if (!$dryRun) {
            $this->logAudit($userId, $result);
        }

        return $result;
    }

    /**
     * Validate meter reading data before ingestion.
     * 
     * @param array $reading Reading data to validate
     * @return bool True if valid, false otherwise
     */
    public function validateReading(array $reading): bool
    {
        // Placeholder implementation
        // Future enhancement: Add validation rules for:
        // - MPAN/meter identifier format
        // - Date/time format and ranges
        // - Reading value ranges and data types
        // - Required fields presence
        
        return true;
    }

    /**
     * Enrich meter reading with external data sources.
     * 
     * @param array $reading Base reading data
     * @param DateTimeImmutable $date Reading date
     * @return array Enriched reading data
     */
    public function enrichReading(array $reading, DateTimeImmutable $date): array
    {
        // Placeholder implementation
        // Future enhancement: Integrate external data sources:
        // - Gas calorific values
        // - Weather data (temperature, solar radiation)
        // - Carbon intensity factors
        // - Grid demand data
        
        return $reading;
    }

    /**
     * Retry a failed import batch
     */
    public function retryBatch(string $batchId, string $filePath, string $format, ?int $userId = null): IngestionResult
    {
        // Validate batch ID to prevent injection
        if (!preg_match('/^[a-f0-9\-]{36}$/i', $batchId)) {
            throw new Exception('Invalid batch identifier format');
        }
        
        // Fetch original batch info
        $stmt = $this->pdo->prepare('
            SELECT id, new_values, retry_count 
            FROM audit_logs 
            WHERE action = :action 
            AND JSON_EXTRACT(new_values, "$.batch_id") = :batch_id
            ORDER BY created_at DESC 
            LIMIT 1
        ');
        $stmt->execute([
            'action' => 'import_csv',
            'batch_id' => $batchId,
        ]);
        
        $originalBatch = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$originalBatch) {
            throw new Exception('Original batch not found');
        }
        
        $retryCount = (int) ($originalBatch['retry_count'] ?? 0) + 1;
        
        // Generate new batch ID for retry
        $newBatchId = Uuid::uuid4()->toString();
        
        // Perform the ingestion
        $result = $this->ingestFromCsv($filePath, $format, $newBatchId, false, $userId);
        
        // Log the retry with parent batch reference
        $this->logRetry($userId, $result, $batchId, $retryCount);
        
        return $result;
    }
    
    /**
     * Log a retry attempt
     */
    private function logRetry(?int $userId, IngestionResult $result, string $parentBatchId, int $retryCount): void
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO audit_logs (user_id, action, entity_type, new_values, status, retry_count, parent_batch_id)
                VALUES (:user_id, :action, :entity_type, :new_values, :status, :retry_count, :parent_batch_id)
            ');

            // Determine status based on result
            $status = 'completed';
            if ($result->getRecordsFailed() === $result->getRecordsProcessed() && $result->getRecordsProcessed() > 0) {
                $status = 'failed';
            }

            $payload = json_encode([
                'batch_id' => $result->getBatchId(),
                'format' => $result->getMeta()['format'] ?? null,
                'records_processed' => $result->getRecordsProcessed(),
                'records_imported' => $result->getRecordsImported(),
                'records_failed' => $result->getRecordsFailed(),
                'errors' => $result->getErrors(),
                'is_retry' => true,
                'parent_batch_id' => $parentBatchId,
            ], JSON_THROW_ON_ERROR);

            $stmt->execute([
                'user_id' => $userId,
                'action' => 'import_csv',
                'entity_type' => 'import_batch',
                'new_values' => $payload,
                'status' => $status,
                'retry_count' => $retryCount,
                'parent_batch_id' => $parentBatchId,
            ]);
            
            // Update original batch status to 'retrying'
            $updateStmt = $this->pdo->prepare('
                UPDATE audit_logs 
                SET status = :status 
                WHERE action = :action 
                AND JSON_EXTRACT(new_values, "$.batch_id") = :batch_id
                AND parent_batch_id IS NULL
            ');
            $updateStmt->execute([
                'status' => 'retrying',
                'action' => 'import_csv',
                'batch_id' => $parentBatchId,
            ]);
        } catch (PDOException | Exception $exception) {
            // Fallback logging suppressed to avoid interrupting ingestion
        }
    }

    private function normaliseString(string $value): string
    {
        return trim($value);
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        $value = $this->normaliseString($value);
        if ($value === '') {
            return null;
        }

        $formats = ['d/m/Y', 'Y-m-d', DateTime::RFC3339];

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof DateTimeImmutable) {
                return $date;
            }
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return (new DateTimeImmutable())->setTimestamp($timestamp);
        }

        return null;
    }

    private function periodToTime(int $period): string
    {
        $hours = (int) floor(($period - 1) / 2);
        $minutes = (($period - 1) % 2) * 30;

        return sprintf('%02d:%02d:00', $hours, $minutes);
    }

    private function addError(array &$errors, int $rowNumber, string $message): void
    {
        if (count($errors) >= 50) {
            return;
        }

        $errors[] = sprintf('Row %d: %s', $rowNumber, $message);
    }

    private function logAudit(?int $userId, IngestionResult $result): void
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO audit_logs (user_id, action, entity_type, new_values, status, retry_count)
                VALUES (:user_id, :action, :entity_type, :new_values, :status, :retry_count)
            ');

            // Determine status based on result
            $status = 'completed';
            if ($result->getRecordsFailed() === $result->getRecordsProcessed() && $result->getRecordsProcessed() > 0) {
                $status = 'failed';
            } elseif ($result->hasErrors()) {
                $status = 'completed'; // Partial success still marked as completed
            }

            $payload = json_encode([
                'batch_id' => $result->getBatchId(),
                'format' => $result->getMeta()['format'] ?? null,
                'records_processed' => $result->getRecordsProcessed(),
                'records_imported' => $result->getRecordsImported(),
                'records_failed' => $result->getRecordsFailed(),
                'errors' => $result->getErrors(),
            ], JSON_THROW_ON_ERROR);

            $stmt->execute([
                'user_id' => $userId,
                'action' => 'import_csv',
                'entity_type' => 'import_batch',
                'new_values' => $payload,
                'status' => $status,
                'retry_count' => 0,
            ]);
        } catch (PDOException | Exception $exception) {
            // Fallback logging suppressed to avoid interrupting ingestion
        }
    }
}
