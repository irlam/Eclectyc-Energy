<?php
/**
 * eclectyc-energy/app/Domain/Orchestration/OrchestrationResult.php
 * Value object representing the result of an orchestrated execution.
 */

namespace App\Domain\Orchestration;

class OrchestrationResult
{
    public function __construct(
        private bool $success,
        private string $executionId,
        private string $range,
        private float $duration,
        private int $metricsProcessed,
        private int $errors,
        private int $warnings,
        private ?string $errorMessage = null
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getExecutionId(): string
    {
        return $this->executionId;
    }

    public function getRange(): string
    {
        return $this->range;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function getMetricsProcessed(): int
    {
        return $this->metricsProcessed;
    }

    public function getErrors(): int
    {
        return $this->errors;
    }

    public function getWarnings(): int
    {
        return $this->warnings;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'execution_id' => $this->executionId,
            'range' => $this->range,
            'duration' => $this->duration,
            'metrics_processed' => $this->metricsProcessed,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'error_message' => $this->errorMessage,
        ];
    }
}
