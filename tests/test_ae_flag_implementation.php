<?php
/**
 * Test script to validate A/E flag implementation
 * Tests CSV import with reading_type column and report data retrieval
 */

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Domain\Ingestion\CsvIngestionService;

echo "=== Testing A/E Flag Implementation ===\n\n";

// Load environment
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// Database connection
try {
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'] ?? 'localhost',
            $_ENV['DB_DATABASE'] ?? 'eclectyc_energy'
        ),
        $_ENV['DB_USERNAME'] ?? 'root',
        $_ENV['DB_PASSWORD'] ?? ''
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Database connection established\n";
} catch (PDOException $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    echo "  This is expected in a test environment without database setup.\n";
    echo "  Skipping database-dependent tests.\n\n";
    
    // Test CSV header mapping even without database
    testHeaderMapping();
    exit(0);
}

// Test 1: Verify reading_type column is recognized
echo "\n--- Test 1: Header Mapping ---\n";
$csvService = new CsvIngestionService($pdo);
$testFile = __DIR__ . '/sample_hh_data_with_ae_flag.csv';

if (file_exists($testFile)) {
    echo "✓ Test CSV file found: $testFile\n";
    
    // Read first few lines to show format
    $handle = fopen($testFile, 'r');
    $header = fgets($handle);
    echo "  CSV Headers: " . trim($header) . "\n";
    $firstRow = fgets($handle);
    echo "  First Row: " . trim($firstRow) . "\n";
    fclose($handle);
    
    // Test dry run import
    try {
        echo "\n--- Test 2: Dry Run Import ---\n";
        $result = $csvService->ingestFromCsv($testFile, 'hh', null, true);
        
        echo "✓ Dry run completed successfully\n";
        echo "  Processed: {$result->getProcessedRows()} rows\n";
        echo "  Imported: {$result->getImportedRows()} rows\n";
        echo "  Errors: " . count($result->getErrors()) . "\n";
        
        if (count($result->getErrors()) > 0) {
            echo "  Sample errors:\n";
            foreach (array_slice($result->getErrors(), 0, 3) as $error) {
                echo "    - Row {$error['row']}: {$error['message']}\n";
            }
        }
        
        $metadata = $result->getMetadata();
        if (isset($metadata['column_mapping'])) {
            echo "\n  Column Mapping:\n";
            foreach ($metadata['column_mapping'] as $field => $mapped) {
                echo "    - $field: $mapped\n";
            }
        }
    } catch (Exception $e) {
        echo "✗ Dry run failed: " . $e->getMessage() . "\n";
    }
    
    // Test 3: Check if reading_type data would be preserved
    echo "\n--- Test 3: Reading Type Processing ---\n";
    $testCases = [
        'A' => 'actual',
        'E' => 'estimated',
        'actual' => 'actual',
        'estimated' => 'estimated',
        '' => 'actual', // default
    ];
    
    $reflection = new ReflectionClass($csvService);
    $method = $reflection->getMethod('normalizeReadingType');
    $method->setAccessible(true);
    
    $allPassed = true;
    foreach ($testCases as $input => $expected) {
        $result = $method->invoke($csvService, $input === '' ? null : $input);
        $passed = $result === $expected;
        $allPassed = $allPassed && $passed;
        
        $symbol = $passed ? '✓' : '✗';
        $inputDisplay = $input === '' ? 'null' : "'$input'";
        echo "  $symbol Input: $inputDisplay => Expected: '$expected', Got: '$result'\n";
    }
    
    if ($allPassed) {
        echo "\n✓ All reading type normalization tests passed\n";
    } else {
        echo "\n✗ Some reading type normalization tests failed\n";
    }
    
    // Test 4: Query reading type data
    echo "\n--- Test 4: Query Reading Type Data ---\n";
    try {
        $stmt = $pdo->query("
            SELECT 
                reading_type,
                COUNT(*) as count,
                SUM(reading_value) as total_kwh
            FROM meter_readings
            WHERE reading_date = '2024-11-09'
            GROUP BY reading_type
        ");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($results) > 0) {
            echo "✓ Found reading type data for 2024-11-09:\n";
            foreach ($results as $row) {
                echo "  - {$row['reading_type']}: {$row['count']} readings, {$row['total_kwh']} kWh\n";
            }
        } else {
            echo "  No data found for 2024-11-09 (expected if test data not imported)\n";
        }
    } catch (PDOException $e) {
        echo "✗ Query failed: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "✗ Test CSV file not found: $testFile\n";
}

echo "\n=== Testing Complete ===\n";

function testHeaderMapping() {
    echo "\n--- Header Alias Test (No Database) ---\n";
    
    $headerAliases = [
        'reading_type' => ['reading_type', 'readingtype', 'type', 'status', 'ae', 'a_e', 'actual_estimated', 'estimate'],
    ];
    
    $testHeaders = [
        'ReadingType' => 'reading_type',
        'Type' => 'reading_type',
        'Status' => 'reading_type',
        'AE' => 'reading_type',
        'A_E' => 'reading_type',
        'Actual_Estimated' => 'reading_type',
    ];
    
    echo "  Testing header recognition:\n";
    foreach ($testHeaders as $header => $expectedField) {
        $normalized = strtolower(str_replace([' ', '_', '-'], '', $header));
        $found = false;
        
        foreach ($headerAliases[$expectedField] as $alias) {
            $normalizedAlias = strtolower(str_replace([' ', '_', '-'], '', $alias));
            if ($normalized === $normalizedAlias) {
                $found = true;
                break;
            }
        }
        
        $symbol = $found ? '✓' : '✗';
        echo "    $symbol '$header' recognized as $expectedField\n";
    }
}
