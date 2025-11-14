<?php
/**
 * eclectyc-energy/app/Domain/Ingestion/IngestionResult.php
 * Value object capturing the outcome of a data ingestion operation.
 * Last updated: 06/11/2025
 */

namespace App\Domain\Ingestion;

class IngestionResult
{
    private int $recordsProcessed;
    private int $recordsImported;
    /**
     * @var array<int, string>
     */
    private array $errors;
    private ?string $batchId;
    private bool $dryRun;
    /**
     * @var array<string, mixed>
     */
    private array $meta;

    /**
     * @param int $recordsProcessed Total number of records processed
     * @param int $recordsImported Number of records successfully imported
     * @param array<int, string> $errors Array of error messages
     */
    public function __construct(int $recordsProcessed, int $recordsImported, array $errors = [], ?string $batchId = null, bool $dryRun = false, array $meta = [])
    {
        $this->recordsProcessed = $recordsProcessed;
        $this->recordsImported = $recordsImported;
        $this->errors = $errors;
        $this->batchId = $batchId;
        $this->dryRun = $dryRun;
        $this->meta = $meta;
    }

    public function getRecordsProcessed(): int
    {
        return $this->recordsProcessed;
    }

    public function getRecordsImported(): int
    {
        return $this->recordsImported;
    }

    public function getRecordsFailed(): int
    {
        return $this->recordsProcessed - $this->recordsImported;
    }

    /**
     * @return array<int, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    public function isSuccessful(): bool
    {
        return !$this->hasErrors() && $this->recordsImported > 0;
    }

    public function getBatchId(): ?string
    {
        return $this->batchId;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    public function toArray(): array
    {
        return [
            'records_processed' => $this->recordsProcessed,
            'records_imported' => $this->recordsImported,
            'records_failed' => $this->getRecordsFailed(),
            'errors' => $this->errors,
            'successful' => $this->isSuccessful(),
            'batch_id' => $this->batchId,
            'dry_run' => $this->dryRun,
            'meta' => $this->meta,
        ];
    }
}
