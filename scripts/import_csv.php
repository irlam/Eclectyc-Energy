<?php
/**
 * eclectyc-energy/scripts/import_csv.php
 * CLI script for importing meter readings from CSV files
 * Last updated: 06/11/2025 22:00:00
 */

use App\Config\Database;
use App\Domain\Ingestion\CsvIngestionService;
use League\Csv\Reader;
use Ramsey\Uuid\Uuid;

class CliProgressBar
{
    private int $total;
    private int $width;
    private bool $finished = false;

    public function __construct(int $total, int $width = 40)
    {
        $this->total = max(1, $total);
        $this->width = max(10, $width);
    }

    public function update(int $processed, int $imported, int $warnings): void
    {
        if ($this->finished) {
            return;
        }

        $clamped = max(0, min($processed, $this->total));
        $ratio = $clamped / $this->total;
        $filled = (int) floor($ratio * $this->width);
        $bar = str_repeat('#', $filled) . str_repeat('-', $this->width - $filled);
        $line = sprintf(
            "\r[%s] %3d%% (%d/%d rows, %d ok, %d warnings)",
            $bar,
            (int) round($ratio * 100),
            $clamped,
            $this->total,
            $imported,
            $warnings
        );

        echo $line;
        fflush(STDOUT);

        if ($clamped >= $this->total) {
            $this->finish();
        }
    }

    public function finish(): void
    {
        if ($this->finished) {
            return;
        }

        $this->finished = true;
        echo "\n";
    }
}

function detectDelimiter(string $filePath): string
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

if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/London');

$args = getopt('f:t:hn', ['file:', 'type:', 'help', 'dry-run']);

if (isset($args['h']) || isset($args['help'])) {
    echo "\n";
    echo "Eclectyc Energy CSV Importer\n";
    echo "============================\n\n";
    echo "Usage: php import_csv.php -f <file> [-t <type>] [--dry-run]\n\n";
    echo "Options:\n";
    echo "  -f, --file    Path to CSV file to import (required)\n";
    echo "  -t, --type    Import type: hh (half-hourly) or daily (default: hh)\n";
    echo "  -n, --dry-run Validate only, do not write to the database\n";
    echo "  -h, --help    Show this help message\n\n";
    echo "CSV Format for Half-Hourly (HH) Data:\n";
    echo "  MPAN, Date, HH01, HH02, ..., HH48\n";
    echo "  Where HH01-HH48 are the 48 half-hourly periods\n\n";
    echo "CSV Format for Daily Data:\n";
    echo "  MPAN, Date, Reading\n\n";
    echo "Example:\n";
    echo "  php import_csv.php -f /path/to/readings.csv -t hh\n\n";
    exit(0);
}

$csvFile = $args['f'] ?? $args['file'] ?? null;
$importType = $args['t'] ?? $args['type'] ?? 'hh';
$dryRun = isset($args['n']) || isset($args['dry-run']);

if (!$csvFile) {
    echo "Error: CSV file path is required. Use -h for help.\n";
    exit(1);
}

if (!file_exists($csvFile)) {
    echo "Error: File '$csvFile' not found.\n";
    exit(1);
}

if (!in_array($importType, ['hh', 'daily'], true)) {
    echo "Error: Invalid import type. Must be 'hh' or 'daily'.\n";
    exit(1);
}

echo "\n";
echo "===========================================\n";
echo "  Eclectyc Energy CSV Import\n";
echo "  " . date('d/m/Y H:i:s') . "\n";
echo "===========================================\n\n";
echo "File: $csvFile\n";
echo "Type: $importType\n";
if ($dryRun) {
    echo "Mode: Dry run (validation only)\n";
}
$sizeKb = number_format(filesize($csvFile) / 1024, 2);
echo "Size: {$sizeKb} KB\n";

$detectedDelimiter = detectDelimiter($csvFile);
echo 'Delimiter: ' . ($detectedDelimiter === "\t" ? 'TAB' : $detectedDelimiter) . "\n";

$totalRows = null;
try {
    $countReader = Reader::from($csvFile, 'r');
    $countReader->setDelimiter($detectedDelimiter);
    $countReader->setHeaderOffset(0);
    $totalRows = iterator_count($countReader->getRecords());
    echo 'Rows: ' . $totalRows . "\n";
} catch (\Throwable $rowCountError) {
    echo 'Rows: unavailable (' . $rowCountError->getMessage() . ")\n";
}

if ($totalRows !== null && $totalRows > 0) {
    echo "Progress:\n";
}

echo "\n";

try {
    $db = Database::getConnection();
    if (!$db) {
        throw new Exception('Failed to connect to database');
    }

    try {
    $preview = Reader::from($csvFile, 'r');
        $preview->setDelimiter($detectedDelimiter);
        $preview->setHeaderOffset(0);
        $headers = $preview->getHeader();
        echo "Headers: " . implode(', ', $headers) . "\n\n";
    } catch (\Throwable $previewError) {
        echo "Headers: unavailable (" . $previewError->getMessage() . ")\n\n";
    }

    $service = new CsvIngestionService($db);
    $batchId = Uuid::uuid4()->toString();

    $progressBar = null;
    $progressCallback = null;

    if ($totalRows !== null && $totalRows > 0) {
        $progressBar = new CliProgressBar($totalRows);
        $progressCallback = function (int $processed, int $imported, int $warnings) use ($progressBar) {
            if ($progressBar !== null) {
                $progressBar->update($processed, $imported, $warnings);
            }
        };
    }

    $result = $service->ingestFromCsv($csvFile, $importType, $batchId, $dryRun, null, $progressCallback);

    if ($progressBar !== null) {
        $progressBar->finish();
        echo "\n";
    }

    $effectiveBatch = $result->getBatchId() ?? $batchId;

    echo "Batch ID: $effectiveBatch\n";
    echo "Format: " . ($result->getMeta()['format'] ?? $importType) . "\n";
    echo "\nImport Summary:\n";
    echo "  Rows processed: " . $result->getRecordsProcessed() . "\n";
    echo "  Rows imported: " . $result->getRecordsImported() . "\n";
    echo "  Rows failed: " . $result->getRecordsFailed() . "\n";
    echo "  Data points handled: " . ($result->getMeta()['total_values_processed'] ?? 0) . "\n";

    $errors = $result->getErrors();
    if (!empty($errors)) {
        echo "\nWarnings/Errors (first " . count($errors) . "):\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
    }

    if ($result->hasErrors()) {
        echo "\nImport completed with warnings. Review the details above.\n\n";
        exit(1);
    }

    echo "\nImport completed successfully!\n\n";
    exit(0);
} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    exit(1);
}
