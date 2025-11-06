<?php
/**
 * eclectyc-energy/scripts/aggregate_cron.php
 * Cron job script for aggregating meter readings into daily summaries
 * Last updated: 06/11/2024 14:45:00
 */

use App\Config\Database;

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
$args = getopt('d:v', ['date:', 'verbose']);
$verbose = isset($args['v']) || isset($args['verbose']);
$targetDate = $args['d'] ?? $args['date'] ?? date('Y-m-d', strtotime('-1 day'));

// Validate date
if (!DateTime::createFromFormat('Y-m-d', $targetDate)) {
    echo "Error: Invalid date format. Use YYYY-MM-DD.\n";
    exit(1);
}

// Output header
if ($verbose) {
    echo "\n";
    echo "===========================================\n";
    echo "  Eclectyc Energy Daily Aggregation\n";
    echo "  " . date('d/m/Y H:i:s') . "\n";
    echo "===========================================\n\n";
    echo "Target Date: $targetDate\n\n";
}

try {
    // Connect to database
    $db = Database::getConnection();
    if (!$db) {
        throw new Exception("Failed to connect to database");
    }
    
    // Get all meters
    $meterQuery = $db->query("SELECT id, mpan FROM meters WHERE is_active = TRUE");
    $meters = $meterQuery->fetchAll();
    
    if ($verbose) {
        echo "Found " . count($meters) . " active meters\n\n";
    }
    
    $processedCount = 0;
    $errorCount = 0;
    
    // Process each meter
    foreach ($meters as $meter) {
        try {
            if ($verbose) {
                echo "Processing meter {$meter['mpan']}... ";
            }
            
            // Get readings for the date
            $readingsQuery = $db->prepare("
                SELECT 
                    COUNT(*) as reading_count,
                    SUM(reading_value) as total_consumption,
                    MIN(reading_value) as min_reading,
                    MAX(reading_value) as max_reading
                FROM meter_readings
                WHERE meter_id = ? AND reading_date = ?
            ");
            
            $readingsQuery->execute([$meter['id'], $targetDate]);
            $stats = $readingsQuery->fetch();
            
            if ($stats['reading_count'] > 0) {
                // Calculate peak and off-peak (simplified - real implementation would check time periods)
                $peakQuery = $db->prepare("
                    SELECT SUM(reading_value) as peak_consumption
                    FROM meter_readings
                    WHERE meter_id = ? 
                    AND reading_date = ?
                    AND TIME(reading_time) BETWEEN '07:00:00' AND '23:00:00'
                ");
                
                $peakQuery->execute([$meter['id'], $targetDate]);
                $peakData = $peakQuery->fetch();
                
                $offPeakConsumption = $stats['total_consumption'] - ($peakData['peak_consumption'] ?? 0);
                
                // Insert or update aggregation
                $aggregateQuery = $db->prepare("
                    INSERT INTO daily_aggregations 
                    (meter_id, date, total_consumption, peak_consumption, off_peak_consumption, 
                     min_reading, max_reading, reading_count)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        total_consumption = VALUES(total_consumption),
                        peak_consumption = VALUES(peak_consumption),
                        off_peak_consumption = VALUES(off_peak_consumption),
                        min_reading = VALUES(min_reading),
                        max_reading = VALUES(max_reading),
                        reading_count = VALUES(reading_count),
                        updated_at = CURRENT_TIMESTAMP
                ");
                
                $aggregateQuery->execute([
                    $meter['id'],
                    $targetDate,
                    $stats['total_consumption'] ?? 0,
                    $peakData['peak_consumption'] ?? 0,
                    $offPeakConsumption,
                    $stats['min_reading'],
                    $stats['max_reading'],
                    $stats['reading_count']
                ]);
                
                $processedCount++;
                
                if ($verbose) {
                    echo "OK (Readings: {$stats['reading_count']}, Total: {$stats['total_consumption']} kWh)\n";
                }
            } else {
                if ($verbose) {
                    echo "SKIPPED (No readings)\n";
                }
            }
            
        } catch (Exception $e) {
            $errorCount++;
            if ($verbose) {
                echo "ERROR: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Log to audit
    $auditQuery = $db->prepare("
        INSERT INTO audit_logs (action, entity_type, new_values) 
        VALUES ('daily_aggregation', 'system', ?)
    ");
    
    $auditData = json_encode([
        'date' => $targetDate,
        'meters_processed' => $processedCount,
        'errors' => $errorCount,
        'total_meters' => count($meters)
    ]);
    
    $auditQuery->execute([$auditData]);
    
    // Summary
    if ($verbose) {
        echo "\n";
        echo "Aggregation Complete!\n";
        echo "====================\n";
        echo "Date: $targetDate\n";
        echo "Meters Processed: $processedCount/" . count($meters) . "\n";
        echo "Errors: $errorCount\n\n";
    }
    
    // Exit code
    exit($errorCount > 0 ? 1 : 0);
    
} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    exit(1);
}