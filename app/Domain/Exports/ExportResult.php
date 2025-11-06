<?php
/**
 * eclectyc-energy/app/Domain/Exports/ExportResult.php
 * Summary of data export operation results.
 */

namespace App\Domain\Exports;

class ExportResult
{
    private array $params;
    private array $data = [];
    private int $rowCount = 0;
    private array $errors = [];

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function setRowCount(int $count): void
    {
        $this->rowCount = $count;
    }

    public function registerError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
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
            'params' => $this->params,
            'row_count' => $this->rowCount,
            'data_preview' => array_slice($this->data, 0, 5), // First 5 rows
            'errors' => $this->errors,
        ];
    }
}
