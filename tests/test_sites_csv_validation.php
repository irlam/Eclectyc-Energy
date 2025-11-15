#!/usr/bin/env php
<?php
/**
 * Test Sites CSV Import - Validation Only (No Database)
 * This script tests the CSV parsing and validation logic without database
 */

require_once __DIR__ . '/../vendor/autoload.php';

use League\Csv\Reader;

echo "\n";
echo "===========================================\n";
echo "  Sites CSV Import - Validation Test\n";
echo "===========================================\n\n";

$csvFile = __DIR__ . '/../Test_Sites_Row46_Error.csv';

if (!file_exists($csvFile)) {
    echo "Error: Test CSV file not found: $csvFile\n";
    exit(1);
}

try {
    $reader = Reader::createFromPath($csvFile, 'r');
    $reader->setDelimiter(',');
    $reader->setHeaderOffset(0);
    
    $headers = $reader->getHeader();
    echo "Headers found: " . implode(', ', $headers) . "\n\n";
    
    $rowNumber = 1;
    $errors = [];
    $validRows = 0;
    
    foreach ($reader->getRecords() as $record) {
        $rowNumber++;
        
        // Validate name (required)
        if (empty($record['name'])) {
            $errors[] = "Row $rowNumber: Missing required field 'name'";
            continue;
        }
        
        // Validate company_id (numeric if present)
        if (!empty($record['company_id']) && !is_numeric($record['company_id'])) {
            $errors[] = "Row $rowNumber: Invalid company_id - must be numeric";
            continue;
        }
        
        // Validate created_at date format
        if (!empty($record['created_at'])) {
            $validFormats = [
                'Y-m-d H:i:s',
                'Y-m-d',
                'd/m/Y',
                'd-m-Y',
                'm/d/Y',
                'Y/m/d',
            ];
            
            $validDate = false;
            foreach ($validFormats as $format) {
                $date = DateTime::createFromFormat($format, $record['created_at']);
                if ($date !== false) {
                    $validDate = true;
                    break;
                }
            }
            
            if (!$validDate) {
                // Find column number
                $columnNumber = array_search('created_at', array_keys($record)) + 1;
                $errors[] = "Row $rowNumber, Column $columnNumber: Invalid date format in created_at field";
                continue;
            }
        }
        
        $validRows++;
        echo "✓ Row $rowNumber: {$record['name']}\n";
    }
    
    echo "\n===========================================\n";
    echo "Summary:\n";
    echo "  Total rows: " . ($rowNumber - 1) . "\n";
    echo "  Valid rows: $validRows\n";
    echo "  Errors: " . count($errors) . "\n";
    
    if (!empty($errors)) {
        echo "\nErrors found:\n";
        foreach ($errors as $error) {
            echo "  - $error\n";
        }
        exit(1);
    }
    
    echo "\n✓ All validations passed!\n\n";
    exit(0);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
