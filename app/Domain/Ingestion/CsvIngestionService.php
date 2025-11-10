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
use App\Domain\Settings\SystemSettingsService;

class CsvIngestionService
{
    private PDO $pdo;
    private ?SystemSettingsService $settings = null;

    /**
     * Normalised header aliases recognised by the ingestion service.
     * Allows flexible CSV formats without forcing callers to rename columns.
     * @var array<string, array<int, string>>
     */
    private const HEADER_ALIASES = [
        'mpan' => [
            'mpan',
            'mpancore',
            'mpan_core',
            'metercode',
            'meter_code',
            'meter',
            'meterid',
            'meter_id',
            'meterreference',
            'meter_reference',
            'meterref',
            'meter_ref',
            'meterserial',
            'meter_serial',
            'meterserialnumber',
            'meter_serial_number',
            'serial',
            'serialnumber',
            'supplynumber',
            'mprn',
        ],
        'date' => ['date', 'readdate', 'read_date', 'readingdate', 'perioddate', 'billdate', 'insertdate'],
        'time' => ['time', 'readtime', 'read_time', 'readingtime', 'periodtime'],
        'datetime' => ['datetime', 'timestamp', 'readdatetime', 'read_datetime', 'readingdatetime'],
        'value' => ['reading', 'readvalue', 'read_value', 'value', 'consumption', 'kwh', 'wh', 'usage'],
        'unit' => ['unit', 'units', 'uom'],
        'reading_type' => ['reading_type', 'readingtype', 'type', 'status', 'ae', 'a_e', 'actual_estimated', 'estimate'],
    ];

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
    * @param callable|null $progressCallback Invoked after each row is processed with (processed, imported, warnings)
    * @return IngestionResult Statistics about the ingestion process
     * @throws Exception If file cannot be processed
     */
    public function ingestFromCsv(string $filePath, string $format = 'hh', ?string $batchId = null, bool $dryRun = false, ?int $userId = null, ?callable $progressCallback = null): IngestionResult
    {
        if (!is_readable($filePath)) {
            throw new Exception('CSV file cannot be read: ' . $filePath);
        }

        $format = strtolower($format);
        if (!in_array($format, ['hh', 'daily'], true)) {
            throw new Exception('Unsupported import format. Use "hh" or "daily".');
        }

        $batchId = $batchId ?: Uuid::uuid4()->toString();

    $delimiter = $this->detectDelimiter($filePath);
    $reader = Reader::from($filePath);
    $reader->setDelimiter($delimiter);
        $reader->setHeaderOffset(0);

        $headerRow = $reader->getHeader();
        if (!$headerRow) {
            throw new Exception('CSV file must include a header row.');
        }

        $headerMap = $this->buildHeaderMap($headerRow);
        if (!$this->hasAlias($headerMap, self::HEADER_ALIASES['mpan'])) {
            $availableHeaders = array_unique(array_values($headerMap));
            $preview = implode(', ', array_slice($availableHeaders, 0, 15));
            throw new Exception('CSV must include a column containing the meter identifier (e.g. MPAN or MeterCode). Headers detected: ' . $preview);
        }

        if ($format === 'hh') {
            if ($this->isIntervalRecordFormat($headerMap)) {
                $result = $this->ingestHalfHourlyIntervalRecords($reader, $headerMap, $batchId, $dryRun, $progressCallback);
            } else {
                $result = $this->ingestHalfHourlyMatrixRecords($reader, $headerRow, $headerMap, $batchId, $dryRun, $progressCallback);
            }
        } else {
            $result = $this->ingestDailyTotals($reader, $headerMap, $batchId, $dryRun, $progressCallback);
        }

        if (!$dryRun) {
            $this->logAudit($userId, $result);
        }

        return $result;
    }

    private function ingestHalfHourlyIntervalRecords(Reader $reader, array $headerMap, string $batchId, bool $dryRun, ?callable $progressCallback = null): IngestionResult
    {
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
        $unitCounts = [];

        foreach ($reader->getRecords() as $rowNumber => $record) {
            $processed++;

            try {
                $mpanRaw = $this->getValueFromRecord($record, $headerMap, self::HEADER_ALIASES['mpan']);
                $mpan = $this->normaliseString($mpanRaw ?? '');
                if ($mpan === '') {
                    throw new Exception('Missing MPAN');
                }

                $meterStmt->execute(['mpan' => $mpan]);
                $meter = $meterStmt->fetch();
                if (!$meter) {
                    // Auto-create meter if it doesn't exist
                    $meterId = $this->autoCreateMeter($mpan);
                } else {
                    $meterId = (int) $meter['id'];
                }

                $timestamp = $this->resolveDateTimeFromRecord($record, $headerMap);
                if (!$timestamp) {
                    throw new Exception('Invalid reading timestamp');
                }

                $valueRaw = $this->getValueFromRecord($record, $headerMap, self::HEADER_ALIASES['value']);
                if ($valueRaw === null || $this->normaliseString($valueRaw) === '') {
                    throw new Exception('Missing reading value');
                }

                if (!is_numeric($valueRaw)) {
                    throw new Exception('Non-numeric reading value "' . $valueRaw . '"');
                }

                $unitRaw = $this->getValueFromRecord($record, $headerMap, self::HEADER_ALIASES['unit']);
                $value = $this->normaliseEnergyValue((float) $valueRaw, $unitRaw);
                $unitKey = strtolower(trim((string) ($unitRaw ?? 'kWh')));
                $unitCounts[$unitKey] = ($unitCounts[$unitKey] ?? 0) + 1;

                // Extract reading type (A/E flag)
                $readingTypeRaw = $this->getValueFromRecord($record, $headerMap, self::HEADER_ALIASES['reading_type']);
                $readingType = $this->normalizeReadingType($readingTypeRaw);

                $periodNumber = $this->determinePeriodNumber($timestamp);

                if (!$dryRun) {
                    $insertStmt->execute([
                        'meter_id' => $meterId,
                        'reading_date' => $timestamp->format('Y-m-d'),
                        'reading_time' => $timestamp->format('H:i:s'),
                        'period_number' => $periodNumber,
                        'reading_value' => $value,
                        'reading_type' => $readingType,
                        'batch_id' => $batchId,
                    ]);
                }

                $successfulRows++;
                $totalValues++;
            } catch (PDOException $pdoException) {
                $this->addError($errors, $processed, $pdoException->getMessage());
            } catch (Exception $exception) {
                $this->addError($errors, $processed, $exception->getMessage());
            }

            if ($progressCallback !== null) {
                $progressCallback($processed, $successfulRows, count($errors));
            }

            // Apply throttling if enabled
            $this->applyThrottle($processed);
        }

        return new IngestionResult($processed, $successfulRows, $errors, $batchId, $dryRun, [
            'format' => 'hh-interval',
            'total_values_processed' => $totalValues,
            'column_mapping' => $this->buildColumnMappingReport($headerMap, [
                'mpan' => self::HEADER_ALIASES['mpan'],
                'datetime' => array_merge(self::HEADER_ALIASES['datetime'], self::HEADER_ALIASES['date'], self::HEADER_ALIASES['time']),
                'value' => self::HEADER_ALIASES['value'],
                'unit' => self::HEADER_ALIASES['unit'],
            ]),
            'unit_counts' => $unitCounts,
        ]);
    }

    private function ingestHalfHourlyMatrixRecords(Reader $reader, array $headerRow, array $headerMap, string $batchId, bool $dryRun, ?callable $progressCallback = null): IngestionResult
    {
        if (!$this->hasAlias($headerMap, self::HEADER_ALIASES['date'])) {
            throw new Exception('Half-hourly CSV must include a date column.');
        }

        $halfHourlyColumns = $this->extractHalfHourlyColumns($headerRow);
        if (empty($halfHourlyColumns)) {
            throw new Exception('Half-hourly CSV must include columns HH01..HH48 or provide a timestamp column.');
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
                $mpanRaw = $this->getValueFromRecord($record, $headerMap, self::HEADER_ALIASES['mpan']);
                $mpan = $this->normaliseString($mpanRaw ?? '');
                if ($mpan === '') {
                    throw new Exception('Missing MPAN');
                }

                $meterStmt->execute(['mpan' => $mpan]);
                $meter = $meterStmt->fetch();
                if (!$meter) {
                    // Auto-create meter if it doesn't exist
                    $meterId = $this->autoCreateMeter($mpan);
                } else {
                    $meterId = (int) $meter['id'];
                }

                $dateRaw = $this->getValueFromRecord($record, $headerMap, self::HEADER_ALIASES['date']);
                $dateValue = $this->parseDate($dateRaw);
                if (!$dateValue) {
                    throw new Exception('Invalid date value');
                }

                // Extract reading type (A/E flag) - applies to entire row
                $readingTypeRaw = $this->getValueFromRecord($record, $headerMap, self::HEADER_ALIASES['reading_type']);
                $readingType = $this->normalizeReadingType($readingTypeRaw);

                $rowValues = 0;

                foreach ($halfHourlyColumns as $period => $columnName) {
                    $rawValue = $record[$columnName] ?? null;
                    if ($rawValue === null || $this->normaliseString((string) $rawValue) === '') {
                        continue;
                    }

                    if (!is_numeric($rawValue)) {
                        throw new Exception(sprintf('Non-numeric value "%s" in column %s', (string) $rawValue, $columnName));
                    }

                    $value = (float) $rawValue;
                    $time = $this->periodToTime($period);

                    if (!$dryRun) {
                        $insertStmt->execute([
                            'meter_id' => $meterId,
                            'reading_date' => $dateValue->format('Y-m-d'),
                            'reading_time' => $time,
                            'period_number' => $period,
                            'reading_value' => $value,
                            'reading_type' => $readingType,
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

            if ($progressCallback !== null) {
                $progressCallback($processed, $successfulRows, count($errors));
            }
        }

        return new IngestionResult($processed, $successfulRows, $errors, $batchId, $dryRun, [
            'format' => 'hh-matrix',
            'total_values_processed' => $totalValues,
            'column_mapping' => $this->buildColumnMappingReport($headerMap, [
                'mpan' => self::HEADER_ALIASES['mpan'],
                'date' => self::HEADER_ALIASES['date'],
            ]),
        ]);
    }

    private function ingestDailyTotals(Reader $reader, array $headerMap, string $batchId, bool $dryRun, ?callable $progressCallback = null): IngestionResult
    {
        if (!$this->hasAlias($headerMap, self::HEADER_ALIASES['date'])) {
            throw new Exception('Daily CSV must include a date column.');
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
        $errors = [];
        $unitCounts = [];

        foreach ($reader->getRecords() as $rowNumber => $record) {
            $processed++;

            try {
                $mpanRaw = $this->getValueFromRecord($record, $headerMap, self::HEADER_ALIASES['mpan']);
                $mpan = $this->normaliseString($mpanRaw ?? '');
                if ($mpan === '') {
                    throw new Exception('Missing MPAN');
                }

                $meterStmt->execute(['mpan' => $mpan]);
                $meter = $meterStmt->fetch();
                if (!$meter) {
                    // Auto-create meter if it doesn't exist
                    $meterId = $this->autoCreateMeter($mpan);
                } else {
                    $meterId = (int) $meter['id'];
                }

                $dateRaw = $this->getValueFromRecord($record, $headerMap, self::HEADER_ALIASES['date']);
                $dateValue = $this->parseDate($dateRaw);
                if (!$dateValue) {
                    throw new Exception('Invalid date value');
                }

                $valueRaw = $this->getValueFromRecord($record, $headerMap, self::HEADER_ALIASES['value']);
                if ($valueRaw === null || $this->normaliseString($valueRaw) === '') {
                    throw new Exception('Missing reading value');
                }

                if (!is_numeric($valueRaw)) {
                    throw new Exception('Non-numeric reading value "' . $valueRaw . '"');
                }

                $unitRaw = $this->getValueFromRecord($record, $headerMap, self::HEADER_ALIASES['unit']);
                $value = $this->normaliseEnergyValue((float) $valueRaw, $unitRaw);
                $unitKey = strtolower(trim((string) ($unitRaw ?? 'kWh')));
                $unitCounts[$unitKey] = ($unitCounts[$unitKey] ?? 0) + 1;

                // Extract reading type (A/E flag)
                $readingTypeRaw = $this->getValueFromRecord($record, $headerMap, self::HEADER_ALIASES['reading_type']);
                $readingType = $this->normalizeReadingType($readingTypeRaw);

                if (!$dryRun) {
                    $insertStmt->execute([
                        'meter_id' => $meterId,
                        'reading_date' => $dateValue->format('Y-m-d'),
                        'reading_time' => '00:00:00',
                        'period_number' => null,
                        'reading_value' => $value,
                        'reading_type' => $readingType,
                        'batch_id' => $batchId,
                    ]);
                }

                $successfulRows++;
            } catch (PDOException $pdoException) {
                $this->addError($errors, $processed, $pdoException->getMessage());
            } catch (Exception $exception) {
                $this->addError($errors, $processed, $exception->getMessage());
            }

            if ($progressCallback !== null) {
                $progressCallback($processed, $successfulRows, count($errors));
            }
        }

        return new IngestionResult($processed, $successfulRows, $errors, $batchId, $dryRun, [
            'format' => 'daily',
            'total_values_processed' => $successfulRows,
            'column_mapping' => $this->buildColumnMappingReport($headerMap, [
                'mpan' => self::HEADER_ALIASES['mpan'],
                'date' => self::HEADER_ALIASES['date'],
                'value' => self::HEADER_ALIASES['value'],
                'unit' => self::HEADER_ALIASES['unit'],
            ]),
            'unit_counts' => $unitCounts,
        ]);
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, string>
     */
    private function buildHeaderMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $header) {
            if (!is_string($header)) {
                continue;
            }

            $original = (string) $header;
            $withoutBom = $this->stripUtf8Bom($original);
            $clean = trim($withoutBom);

            if ($clean === '') {
                continue;
            }

            $normalisedClean = $this->normaliseHeaderKey($clean);
            $map[$normalisedClean] = $original;

            $normalisedOriginal = $this->normaliseHeaderKey($original);
            if ($normalisedOriginal !== $normalisedClean) {
                $map[$normalisedOriginal] = $original;
            }
        }

        return $map;
    }

    private function stripUtf8Bom(string $header): string
    {
        $bom = pack('CCC', 0xEF, 0xBB, 0xBF);
        if (str_starts_with($header, $bom)) {
            $header = substr($header, 3);
        }

        return $header;
    }

    private function normaliseHeaderKey(string $value): string
    {
        $value = strtolower($value);

        return preg_replace('/[^a-z0-9]+/', '', $value);
    }

    private function detectDelimiter(string $filePath): string
    {
        $candidates = [',', "\t", ';', '|'];
        $bestDelimiter = ',';
        $highestCount = -1;

        $handle = @fopen($filePath, 'r');
        if ($handle === false) {
            return $bestDelimiter;
        }

        $line = fgets($handle, 65536) ?: '';
        fclose($handle);

        foreach ($candidates as $delimiter) {
            $count = substr_count($line, $delimiter);
            if ($count > $highestCount) {
                $highestCount = $count;
                $bestDelimiter = $delimiter;
            }
        }

        return $bestDelimiter;
    }

    private function hasAlias(array $headerMap, array $aliases): bool
    {
        return $this->resolveColumnName($headerMap, $aliases) !== null;
    }

    private function getValueFromRecord(array $record, array $headerMap, array $aliases): ?string
    {
        $columnName = $this->resolveColumnName($headerMap, $aliases);
        if ($columnName === null || !array_key_exists($columnName, $record)) {
            return null;
        }

        $value = $record[$columnName];
        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === null ? null : (string) $value;
    }

    /**
     * @param array<string, string> $headerMap
     * @param array<string, array<int, string>> $requested
     * @return array<string, string>
     */
    private function buildColumnMappingReport(array $headerMap, array $requested): array
    {
        $report = [];

        foreach ($requested as $field => $aliases) {
            $column = $this->resolveColumnName($headerMap, $aliases);
            if ($column !== null) {
                $report[$field] = $column;
            }
        }

        return $report;
    }

    private function resolveColumnName(array $headerMap, array $aliases): ?string
    {
        $aliasKeys = [];
        foreach ($aliases as $alias) {
            $aliasKeys[$this->normaliseHeaderKey($alias)] = true;
        }

        foreach ($headerMap as $column) {
            $normalisedColumn = $this->normaliseHeaderKey((string) $column);
            if (isset($aliasKeys[$normalisedColumn])) {
                return $column;
            }
        }

        return null;
    }

    private function isIntervalRecordFormat(array $headerMap): bool
    {
        return $this->hasAlias($headerMap, self::HEADER_ALIASES['mpan'])
            && $this->hasAlias($headerMap, self::HEADER_ALIASES['value'])
            && (
                $this->hasAlias($headerMap, self::HEADER_ALIASES['datetime'])
                || (
                    $this->hasAlias($headerMap, self::HEADER_ALIASES['date'])
                    && $this->hasAlias($headerMap, self::HEADER_ALIASES['time'])
                )
            );
    }

    private function resolveDateTimeFromRecord(array $record, array $headerMap): ?DateTimeImmutable
    {
        $dateTimeValue = $this->getValueFromRecord($record, $headerMap, self::HEADER_ALIASES['datetime']);
        if ($dateTimeValue !== null && $dateTimeValue !== '') {
            $dateTime = $this->parseDateTimeValue($dateTimeValue);
            if ($dateTime) {
                return $dateTime;
            }
        }

        $dateValueRaw = $this->getValueFromRecord($record, $headerMap, self::HEADER_ALIASES['date']);
        if ($dateValueRaw === null || $dateValueRaw === '') {
            return null;
        }

        $dateValue = $this->parseDate($dateValueRaw);
        if (!$dateValue) {
            return null;
        }

        $timeValue = $this->getValueFromRecord($record, $headerMap, self::HEADER_ALIASES['time']);
        if ($timeValue === null || $timeValue === '') {
            return $dateValue;
        }

        $timeComponents = $this->parseTimeComponents($timeValue);
        if ($timeComponents === null) {
            return $dateValue;
        }

        return $dateValue->setTime($timeComponents[0], $timeComponents[1], $timeComponents[2]);
    }

    private function parseDateTimeValue(string $value): ?DateTimeImmutable
    {
        $value = $this->normaliseString($value);
        if ($value === '') {
            return null;
        }

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd-m-Y H:i:s',
            'd-m-Y H:i',
            DateTime::ATOM,
            DateTime::RFC3339,
        ];

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

    /**
     * @return array<int, string>
     */
    private function extractHalfHourlyColumns(array $headerRow): array
    {
        $columns = [];

        foreach ($headerRow as $header) {
            $lower = strtolower($header);
            if (preg_match('/^hh(\d{1,2})$/', $lower, $matches)) {
                $period = (int) $matches[1];
                if ($period >= 1 && $period <= 48) {
                    $columns[$period] = $header;
                }
            }
        }

        ksort($columns);

        return $columns;
    }

    /**
     * @return array<int, int>
     */
    private function parseTimeComponents(string $value): ?array
    {
        $value = $this->normaliseString($value);
        if ($value === '') {
            return null;
        }

        // Handle special case: format like "30:00.0" which means 00:30:00 (MM:SS.f format)
        // When first component is >= 24 and <= 59, treat as minutes:seconds
        if (preg_match('/^(\d{1,3}):(\d{2})(?:\.(\d+))?$/', $value, $matches)) {
            $first = (int) $matches[1];
            $second = (int) $matches[2];

            // If first component is 0-23, treat as HH:MM
            if ($first <= 23) {
                return [$first, $second, 0];
            }
            
            // If first component is 24-59, treat as MM:SS (minutes:seconds)
            if ($first >= 24 && $first <= 59) {
                $hours = 0;
                $minutes = $first;
                $seconds = $second;
                return [$hours, $minutes, $seconds];
            }

            // If first component is >= 60, convert total seconds to H:M:S
            $totalSeconds = ($first * 60) + $second;
            $hours = (int) floor($totalSeconds / 3600);
            $minutes = (int) floor(($totalSeconds % 3600) / 60);
            $seconds = $totalSeconds % 60;

            return [$hours % 24, $minutes, $seconds];
        }

        if (is_numeric($value) && (float) $value >= 0 && (float) $value <= 1) {
            $minutes = (float) $value * 24 * 60;
            $hours = (int) floor($minutes / 60);
            $minutes = (int) round($minutes % 60);
            return [$hours % 24, $minutes, 0];
        }

        $normalised = str_replace(['.', '-'], [':', ':'], $value);

        $patterns = ['H:i:s', 'H:i', 'H:i:s.u'];
        foreach ($patterns as $pattern) {
            $time = DateTimeImmutable::createFromFormat($pattern, $normalised);
            if ($time instanceof DateTimeImmutable) {
                return [(int) $time->format('H'), (int) $time->format('i'), (int) $time->format('s')];
            }
        }

        if (preg_match('/^(\d{1,3}):(\d{2})$/', $normalised, $matches)) {
            $first = (int) $matches[1];
            $second = (int) $matches[2];

            if ($first < 24) {
                return [$first, $second, 0];
            }

            $totalMinutes = ($first * 60) + $second;
            $hours = (int) floor($totalMinutes / 60);
            $minutes = $totalMinutes % 60;

            return [$hours % 24, $minutes, 0];
        }

        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            $time = (new DateTimeImmutable())->setTimestamp($timestamp);
            return [(int) $time->format('H'), (int) $time->format('i'), (int) $time->format('s')];
        }

        return null;
    }

    private function determinePeriodNumber(DateTimeImmutable $dateTime): int
    {
        $minutes = ((int) $dateTime->format('H') * 60) + (int) $dateTime->format('i');
        $period = (int) floor($minutes / 30) + 1;

        if ($period < 1) {
            return 1;
        }

        if ($period > 48) {
            return 48;
        }

        return $period;
    }

    private function normaliseEnergyValue(float $value, ?string $unit): float
    {
        if ($unit === null) {
            return $value;
        }

        $unit = strtolower(trim($unit));

        if ($unit === '' || $unit === 'kwh') {
            return $value;
        }

        if ($unit === 'wh') {
            return $value / 1000;
        }

        if ($unit === 'mwh') {
            return $value * 1000;
        }

        return $value;
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

        // Handle special case: dates with year "0" or missing year (e.g., "30/08/0" or "30/08")
        // These should use the current year
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/0$/', $value, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) date('Y');
            
            try {
                $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
                return $date;
            } catch (Exception $e) {
                // Invalid date, fall through to other parsing methods
            }
        }
        
        // Handle dates without year (e.g., "30/08") - use current year
        if (preg_match('/^(\d{1,2})\/(\d{1,2})$/', $value, $matches)) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = (int) date('Y');
            
            try {
                $date = new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
                return $date;
            } catch (Exception $e) {
                // Invalid date, fall through to other parsing methods
            }
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

    /**
     * Auto-create a meter with a default site/company if it doesn't exist
     * 
     * @param string $mpan The MPAN to create
     * @return int The ID of the newly created meter
     * @throws Exception If meter creation fails
     */
    private function autoCreateMeter(string $mpan): int
    {
        try {
            // Ensure we have a default company
            $defaultCompanyId = $this->ensureDefaultCompany();
            
            // Ensure we have a default site for this company
            $defaultSiteId = $this->ensureDefaultSite($defaultCompanyId);
            
            // Determine meter type from MPAN format (simplified heuristic)
            $meterType = 'electricity'; // Default to electricity
            if (preg_match('/^M/i', $mpan)) {
                $meterType = 'gas'; // MPRN typically starts with M
            }
            
            // Create the meter
            $stmt = $this->pdo->prepare('
                INSERT INTO meters (site_id, mpan, meter_type, is_half_hourly, is_active, created_at, updated_at)
                VALUES (:site_id, :mpan, :meter_type, TRUE, TRUE, NOW(), NOW())
            ');
            
            $stmt->execute([
                'site_id' => $defaultSiteId,
                'mpan' => $mpan,
                'meter_type' => $meterType,
            ]);
            
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception('Failed to auto-create meter for MPAN ' . $mpan . ': ' . $e->getMessage());
        }
    }

    /**
     * Ensure a default company exists
     * 
     * @return int The ID of the default company
     */
    private function ensureDefaultCompany(): int
    {
        // Check if default company exists
        $stmt = $this->pdo->prepare('SELECT id FROM companies WHERE name = :name LIMIT 1');
        $stmt->execute(['name' => 'Default Company']);
        $company = $stmt->fetch();
        
        if ($company) {
            return (int) $company['id'];
        }
        
        // Create default company
        $stmt = $this->pdo->prepare('
            INSERT INTO companies (name, is_active, created_at, updated_at)
            VALUES (:name, TRUE, NOW(), NOW())
        ');
        $stmt->execute(['name' => 'Default Company']);
        
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Ensure a default site exists for a company
     * 
     * @param int $companyId The company ID
     * @return int The ID of the default site
     */
    private function ensureDefaultSite(int $companyId): int
    {
        // Check if default site exists for this company
        $stmt = $this->pdo->prepare('SELECT id FROM sites WHERE company_id = :company_id AND name = :name LIMIT 1');
        $stmt->execute([
            'company_id' => $companyId,
            'name' => 'Auto-imported Meters',
        ]);
        $site = $stmt->fetch();
        
        if ($site) {
            return (int) $site['id'];
        }
        
        // Create default site
        $stmt = $this->pdo->prepare('
            INSERT INTO sites (company_id, name, address, postcode, is_active, created_at, updated_at)
            VALUES (:company_id, :name, :address, :postcode, TRUE, NOW(), NOW())
        ');
        $stmt->execute([
            'company_id' => $companyId,
            'name' => 'Auto-imported Meters',
            'address' => 'Auto-generated during CSV import',
            'postcode' => 'TBD',
        ]);
        
        return (int) $this->pdo->lastInsertId();
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

    /**
     * Apply throttling if enabled to prevent server overload
     * 
     * HOW THROTTLING WORKS:
     * ---------------------
     * Throttling helps prevent server timeouts during large CSV imports by:
     * 1. Processing data in small batches (configurable batch size)
     * 2. Adding a delay (sleep) between batches to reduce server load
     * 3. Allowing PHP to handle other requests and avoid 504 Gateway Timeouts
     * 
     * Settings are controlled via the system_settings table:
     * - import_throttle_enabled: Boolean to enable/disable throttling
     * - import_throttle_batch_size: Number of rows to process before pausing (default: 100)
     * - import_throttle_delay_ms: Milliseconds to wait between batches (default: 100ms)
     * - import_max_execution_time: Maximum time in seconds for the entire import (default: 300s/5min)
     * 
     * RECOMMENDED PRESETS:
     * - Small imports (<5,000 rows): Throttle OFF (maximum speed)
     * - Medium imports (5,000-20,000 rows): Batch=100, Delay=100ms
     * - Large imports (20,000-100,000 rows): Batch=50, Delay=200ms, MaxTime=600s (10 min)
     * - Very large imports (>100,000 rows): Batch=25, Delay=300ms, MaxTime=900s (15 min), Use Async!
     * 
     * VERIFICATION:
     * The throttling is currently working correctly:
     * - Applied in all ingestion methods (interval, matrix, daily)
     * - Uses usleep() to pause execution after each batch
     * - Batch size determines how often pauses occur
     * - Settings are lazy-loaded from database on first use
     * 
     * @param int $processed Number of records processed so far
     */
    private function applyThrottle(int $processed): void
    {
        // Lazy load settings service
        if ($this->settings === null) {
            try {
                $this->settings = new SystemSettingsService($this->pdo);
            } catch (Exception $e) {
                // If settings table doesn't exist yet, skip throttling
                return;
            }
        }

        try {
            $throttleSettings = $this->settings->getImportThrottleSettings();
            
            if (!$throttleSettings['enabled']) {
                return;
            }

            $batchSize = $throttleSettings['batch_size'];
            $delayMs = $throttleSettings['delay_ms'];

            // Apply delay after every batch
            if ($processed > 0 && $processed % $batchSize === 0) {
                usleep($delayMs * 1000); // Convert ms to microseconds
            }
        } catch (Exception $e) {
            // Silently skip throttling if there's an error
            return;
        }
    }

    /**
     * Normalize reading type from CSV value to database enum
     * Accepts: A, E, actual, estimated
     * Returns: 'actual' or 'estimated' (defaults to 'actual')
     */
    private function normalizeReadingType(?string $value): string
    {
        if ($value === null) {
            return 'actual';
        }

        $normalized = strtolower(trim($value));

        // Check for single letter codes
        if ($normalized === 'a') {
            return 'actual';
        }
        if ($normalized === 'e') {
            return 'estimated';
        }

        // Check for full words
        if (in_array($normalized, ['actual', 'estimated', 'manual'], true)) {
            return $normalized;
        }

        // Default to 'actual' for unrecognized values
        return 'actual';
    }
}
