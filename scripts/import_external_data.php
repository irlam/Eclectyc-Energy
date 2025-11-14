<?php
/**
 * eclectyc-energy/scripts/import_external_data.php
 * CLI script to import external datasets (temperature, calorific values, carbon intensity).
 * Last updated: 07/11/2025
 */

use App\Config\Database;
use App\Domain\External\ExternalDataService;

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script can only be run from command line.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenvClass = 'Dotenv\\Dotenv';
if (class_exists($dotenvClass)) {
    $dotenv = $dotenvClass::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
}

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/London');

function runExternalDataImport(): int
{
    $args = getopt('t:f:l:r:', ['type:', 'file:', 'location:', 'region:', 'help']);
    
    if (isset($args['help'])) {
        printHelp();
        return 0;
    }
    
    $type = $args['t'] ?? $args['type'] ?? null;
    $file = $args['f'] ?? $args['file'] ?? null;
    $location = $args['l'] ?? $args['location'] ?? null;
    $region = $args['r'] ?? $args['region'] ?? null;
    
    if (!$type || !$file) {
        fwrite(STDERR, "Error: --type and --file are required.\n");
        printHelp();
        return 1;
    }
    
    if (!file_exists($file)) {
        fwrite(STDERR, "Error: File not found: {$file}\n");
        return 1;
    }
    
    $pdo = Database::getConnection();
    if (!$pdo) {
        throw new RuntimeException('Failed to connect to database');
    }
    
    $service = new ExternalDataService($pdo);
    
    try {
        switch ($type) {
            case 'temperature':
                if (!$location) {
                    fwrite(STDERR, "Error: --location is required for temperature data.\n");
                    return 1;
                }
                return importTemperatureData($service, $file, $location);
                
            case 'calorific':
                if (!$region) {
                    fwrite(STDERR, "Error: --region is required for calorific value data.\n");
                    return 1;
                }
                return importCalorificData($service, $file, $region);
                
            case 'carbon':
                if (!$region) {
                    fwrite(STDERR, "Error: --region is required for carbon intensity data.\n");
                    return 1;
                }
                return importCarbonData($service, $file, $region);
                
            default:
                fwrite(STDERR, "Error: Unknown type '{$type}'. Use temperature, calorific, or carbon.\n");
                return 1;
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "Error: {$e->getMessage()}\n");
        return 1;
    }
}

function importTemperatureData(ExternalDataService $service, string $file, string $location): int
{
    echo "Importing temperature data for location: {$location}\n";
    
    $csv = fopen($file, 'r');
    if (!$csv) {
        fwrite(STDERR, "Error: Could not open file.\n");
        return 1;
    }
    
    // Skip header
    $header = fgetcsv($csv);
    
    $imported = 0;
    $errors = 0;
    
    while (($row = fgetcsv($csv)) !== false) {
        try {
            // Expected format: date, avg_temp, min_temp, max_temp
            if (count($row) < 4) {
                $errors++;
                continue;
            }
            
            $date = new DateTimeImmutable($row[0]);
            $data = [
                'avg_temperature' => (float) $row[1],
                'min_temperature' => (float) $row[2],
                'max_temperature' => (float) $row[3],
                'source' => 'csv_import',
            ];
            
            $service->storeTemperatureData($location, $date, $data);
            $imported++;
            
        } catch (Exception $e) {
            $errors++;
            fwrite(STDERR, "Row error: {$e->getMessage()}\n");
        }
    }
    
    fclose($csv);
    
    echo "Import complete: {$imported} records imported, {$errors} errors.\n";
    return $errors > 0 ? 1 : 0;
}

function importCalorificData(ExternalDataService $service, string $file, string $region): int
{
    echo "Importing calorific value data for region: {$region}\n";
    
    $csv = fopen($file, 'r');
    if (!$csv) {
        fwrite(STDERR, "Error: Could not open file.\n");
        return 1;
    }
    
    // Skip header
    $header = fgetcsv($csv);
    
    $imported = 0;
    $errors = 0;
    
    while (($row = fgetcsv($csv)) !== false) {
        try {
            // Expected format: date, calorific_value, unit
            if (count($row) < 2) {
                $errors++;
                continue;
            }
            
            $date = new DateTimeImmutable($row[0]);
            $data = [
                'calorific_value' => (float) $row[1],
                'unit' => $row[2] ?? 'MJ/m3',
                'source' => 'csv_import',
            ];
            
            $service->storeCalorificValues($region, $date, $data);
            $imported++;
            
        } catch (Exception $e) {
            $errors++;
            fwrite(STDERR, "Row error: {$e->getMessage()}\n");
        }
    }
    
    fclose($csv);
    
    echo "Import complete: {$imported} records imported, {$errors} errors.\n";
    return $errors > 0 ? 1 : 0;
}

function importCarbonData(ExternalDataService $service, string $file, string $region): int
{
    echo "Importing carbon intensity data for region: {$region}\n";
    
    $csv = fopen($file, 'r');
    if (!$csv) {
        fwrite(STDERR, "Error: Could not open file.\n");
        return 1;
    }
    
    // Skip header
    $header = fgetcsv($csv);
    
    $imported = 0;
    $errors = 0;
    
    while (($row = fgetcsv($csv)) !== false) {
        try {
            // Expected format: datetime, intensity, forecast (optional), actual (optional)
            if (count($row) < 2) {
                $errors++;
                continue;
            }
            
            $datetime = new DateTimeImmutable($row[0]);
            $data = [
                'intensity' => (float) $row[1],
                'forecast' => isset($row[2]) && $row[2] !== '' ? (float) $row[2] : null,
                'actual' => isset($row[3]) && $row[3] !== '' ? (float) $row[3] : null,
                'source' => 'csv_import',
            ];
            
            $service->storeCarbonIntensity($region, $datetime, $data);
            $imported++;
            
        } catch (Exception $e) {
            $errors++;
            fwrite(STDERR, "Row error: {$e->getMessage()}\n");
        }
    }
    
    fclose($csv);
    
    echo "Import complete: {$imported} records imported, {$errors} errors.\n";
    return $errors > 0 ? 1 : 0;
}

function printHelp(): void
{
    echo "External Data Import Script\n";
    echo "===========================\n\n";
    echo "Usage: php import_external_data.php [options]\n\n";
    echo "Options:\n";
    echo "  -t, --type <type>           Data type (temperature, calorific, carbon)\n";
    echo "  -f, --file <file>           CSV file path\n";
    echo "  -l, --location <location>   Location name (for temperature data)\n";
    echo "  -r, --region <region>       Region name (for calorific/carbon data)\n";
    echo "  --help                      Show this help message\n\n";
    echo "CSV Format:\n";
    echo "  Temperature: date, avg_temp, min_temp, max_temp\n";
    echo "  Calorific:   date, calorific_value, unit\n";
    echo "  Carbon:      datetime, intensity, forecast, actual\n\n";
    echo "Examples:\n";
    echo "  php import_external_data.php -t temperature -f temp.csv -l London\n";
    echo "  php import_external_data.php -t calorific -f cv.csv -r UK_SE\n";
    echo "  php import_external_data.php -t carbon -f carbon.csv -r GB\n";
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $exitCode = runExternalDataImport();
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n");
        $exitCode = 1;
    }
    
    exit($exitCode);
}
