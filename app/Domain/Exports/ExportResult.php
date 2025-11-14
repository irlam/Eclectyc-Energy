<?php
/**
 * eclectyc-energy/app/Domain/Exports/ExportResult.php
 * Value object representing the result of an export operation.
 * Last updated: 06/11/2025
 */

namespace App\Domain\Exports;

class ExportResult
{
    private string $format;
    private int $recordsExported;
    private int $fileSize;
    private bool $successful;
    /**
     * @var array<int, string>
     */
    private array $errors;

    /**
     * @param string $format Export format (csv, excel, pdf, sftp, etc.)
     * @param int $recordsExported Number of records exported
     * @param int $fileSize Size of the exported file in bytes
     * @param bool $successful Whether the export was successful
     * @param array<int, string> $errors Array of error messages
     */
    public function __construct(string $format, int $recordsExported, int $fileSize, bool $successful, array $errors = [])
    {
        $this->format = $format;
        $this->recordsExported = $recordsExported;
        $this->fileSize = $fileSize;
        $this->successful = $successful;
        $this->errors = $errors;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getRecordsExported(): int
    {
        return $this->recordsExported;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * @return array<int, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFileSizeFormatted(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->fileSize;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    public function toArray(): array
    {
        return [
            'format' => $this->format,
            'records_exported' => $this->recordsExported,
            'file_size' => $this->fileSize,
            'file_size_formatted' => $this->getFileSizeFormatted(),
            'successful' => $this->successful,
            'errors' => $this->errors,
        ];
    }
}
