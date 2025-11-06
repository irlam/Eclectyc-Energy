<?php
/**
 * eclectyc-energy/scripts/import_csv.php
 * CLI script for importing meter readings from CSV files
 * Last updated: 06/11/2024 14:45:00
 */

use App\Config\Database;
use League\Csv\Reader;
use Ramsey\Uuid\Uuid;

// Check if running from CLI
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line.');
}

// Autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load environment
$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// Set timezone
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/London');

// Parse arguments
$args = getopt('f:t:h', ['file:', 'type:', 'help']);

// Show help
if (isset($args['h']) || isset($args['help'])) {
    echo "\n";
    echo "Eclectyc Energy CSV Importer\n";
    echo "============================\n\n";
    echo "Usage: php import_csv.php -f <file> [-t <type>]\n\n";
    echo "Options:\n";
    echo "  -f, --file    Path to CSV file to import (required)\n";
    echo "  -t, --type    Import type: hh (half-hourly) or daily (default: hh)\n";
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

// Validate arguments
$csvFile = $args['f'] ?? $args['file'] ?? null;
$importType = $args['t'] ?? $args['type'] ?? 'hh';

if (!$csvFile) {
    echo "Error: CSV file path is required. Use -h for help.\n";
    exit(1);
}

if (!file_exists($csvFile)) {
    echo "Error: File '$csvFile' not found.\n";
    exit(1);
}

if (!in_array($importType, ['hh', 'daily'])) {
    echo "Error: Invalid import type. Must be 'hh' or 'daily'.\n";
    exit(1);
}

// Start import
echo "\n";
echo "===========================================\n";
echo "  Eclectyc Energy CSV Import\n";
echo "  " . date('d/m/Y H:i:s') . "\n";
echo "===========================================\n\n";
echo "File: $csvFile\n";
echo "Type: $importType\n";
echo "Size: " . number_format(filesize($csvFile) / 1024, 2) . " KB\n\n";

try {
    // Connect to database
    $db = Database::getConnection();
    if (!$db) {
        throw new Exception("Failed to connect to database");
    }
    
    // Create import batch ID
    $batchId = Uuid::uuid4()->toString();
    echo "Batch ID: $batchId\n\n";
    
    // Read CSV
    $csv = Reader::createFromPath($csvFile, 'r');
    $csv->setHeaderOffset(0);
    
    // Get headers
    $headers = $csv->getHeader();
    echo "Headers: " . implode(', ', $headers) . "\n\n";
    
    // Prepare statements
    $meterQuery = $db->prepare("SELECT id FROM meters WHERE mpan = ?");
    
    $insertQuery = $db->prepare("
        INSERT INTO meter_readings 
        (meter_id, reading_date, reading_time, period_number, reading_value, reading_type, import_batch_id) 
        VALUES (?, ?, ?, ?, ?, 'actual', ?)
        ON DUPLICATE KEY UPDATE 
        reading_value = VALUES(reading_value),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    // Process records
    $totalRows = 0;
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    
    echo "Processing records...\n";
    
    foreach ($csv->getRecords() as $offset => $record) {
        $totalRows++;
        
        // Show progress every 100 rows
        if ($totalRows % 100 == 0) {
            echo "  Processed $totalRows rows...\n";
        }
        
        try {
            // Get MPAN
            $mpan = trim($record['MPAN'] ?? $record['mpan'] ?? '');
            if (empty($mpan)) {
                throw new Exception("Missing MPAN at row $totalRows");
            }
            
            // Get meter ID
            $meterQuery->execute([$mpan]);
            $meter = $meterQuery->fetch();
            
            if (!$meter) {
                throw new Exception("Meter not found for MPAN: $mpan");
            }
            
            $meterId = $meter['id'];
            
            // Get date
            $dateStr = trim($record['Date'] ?? $record['date'] ?? '');
            $date = DateTime::createFromFormat('d/m/Y', $dateStr);
            
            if (!$date) {
                // Try alternative formats
                $date = DateTime::createFromFormat('Y-m-d', $dateStr);
            }
            
            if (!$date) {
                throw new Exception("Invalid date format: $dateStr");
            }
            
            $dateFormatted = $date->format('Y-m-d');
            
            // Process based on type
            if ($importType === 'hh') {
                // Half-hourly data
                for ($period = 1; $period <= 48; $period++) {
                    $columnName = sprintf('HH%02d', $period);
                    
                    if (isset($record[$columnName])) {
                        $value = floatval($record[$columnName]);
                        
                        // Calculate time for this period
                        $hours = floor(($period - 1) / 2);
                        $minutes = (($period - 1) % 2) * 30;
                        $time = sprintf('%02d:%02d:00', $hours, $minutes);
                        
                        // Insert reading
                        $insertQuery->execute([
                            $meterId,
                            $dateFormatted,
                            $time,
                            $period,
                            $value,
                            $batchId
                        ]);
                    }
                }
            } else {
                // Daily data
                $value = floatval($record['Reading'] ?? $record['reading'] ?? 0);
                
                $insertQuery->execute([
                    $meterId,
                    $dateFormatted,
                    '00:00:00',
                    null,
                    $value,
                    $batchId
                ]);
            }
            
            $successCount++;
            
        } catch (Exception $e) {
            $errorCount++;
            $errors[] = "Row $totalRows: " . $e->getMessage();
            
            // Stop if too many errors
            if ($errorCount > 100) {
                echo "\nToo many errors. Stopping import.\n";
                break;
            }
        }
    }
    
    echo "\n";
    echo "Import Complete!\n";
    echo "===============\n";
    echo "Total Rows: $totalRows\n";
    echo "Successful: $successCount\n";
    echo "Errors: $errorCount\n";
    
    if (!empty($errors)) {
        echo "\nFirst 10 errors:\n";
        foreach (array_slice($errors, 0, 10) as $error) {
            echo "  - $error\n";
        }
    }
    
    // Log to audit table
    $auditQuery = $db->prepare("
        INSERT INTO audit_logs (action, entity_type, entity_id, new_values) 
        VALUES ('csv_import', 'batch', ?, ?)
    ");
    
    $auditData = json_encode([
        'file' => basename($csvFile),
        'type' => $importType,
        'total_rows' => $totalRows,
        'success' => $successCount,
        'errors' => $errorCount
    ]);
    
    $auditQuery->execute([$batchId, $auditData]);
    
    echo "\nBatch ID: $batchId\n";
    echo "Import logged to audit table.\n\n";
    
    exit($errorCount > 0 ? 1 : 0);
    
} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    exit(1);
}