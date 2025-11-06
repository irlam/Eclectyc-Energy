<?php
/**
 * eclectyc-energy/app/Domain/Ingestion/IngestionResult.php
 * Summary of data ingestion operation results.
 */

namespace App\Domain\Ingestion;

class IngestionResult
{
    private int $successCount = 0;
    private int $errorCount = 0;
    private array $errors = [];

    public function incrementSuccessCount(): void
    {
        $this->successCount++;
    }

    public function registerError(string $error): void
    {
        $this->errorCount++;
        $this->errors[] = $error;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return $this->errorCount > 0;
    }

    public function toArray(): array
    {
        return [
            'success_count' => $this->successCount,
            'error_count' => $this->errorCount,
            'errors' => $this->errors,
        ];
    }
}
