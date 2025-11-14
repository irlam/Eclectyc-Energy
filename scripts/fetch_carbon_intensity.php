<?php
/**
 * eclectyc-energy/scripts/fetch_carbon_intensity.php
 * Fetches carbon intensity data from National Grid ESO API
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Database;
use App\Domain\External\ExternalDataService;
use App\Services\CarbonIntensityService;

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Set timezone
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/London');

function logMessage(string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] {$message}\n";
    
    $logPath = $_ENV['LOG_PATH'] ?? 'logs/app.log';
    $logDir = dirname($logPath);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logPath, "[{$timestamp}] CARBON_FETCH: {$message}\n", FILE_APPEND | LOCK_EX);
}

try {
    logMessage("Starting carbon intensity data fetch...");
    
    // Initialize database connection
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Initialize services
    $externalDataService = new ExternalDataService($pdo);
    $carbonService = new CarbonIntensityService($externalDataService);
    
    $action = $argv[1] ?? 'current';
    
    switch ($action) {
        case 'current':
            logMessage("Fetching current carbon intensity...");
            $success = $carbonService->fetchAndStoreCurrentIntensity();
            if ($success) {
                logMessage("✓ Current carbon intensity data stored successfully");
            } else {
                logMessage("✗ Failed to fetch current carbon intensity data");
                exit(1);
            }
            break;
            
        case 'forecast':
            logMessage("Fetching today's carbon intensity forecast...");
            $stored = $carbonService->fetchAndStoreTodaysForecast();
            logMessage("✓ Stored {$stored} forecast periods");
            break;
            
        case 'summary':
            logMessage("Getting carbon intensity dashboard summary...");
            $summary = $carbonService->getDashboardSummary();
            
            if ($summary['current_intensity']) {
                $intensity = $summary['current_intensity'];
                $classification = $summary['classification'];
                logMessage("Current: {$intensity} gCO2/kWh ({$classification['label']}) - Trend: {$summary['trend']}");
            } else {
                logMessage("No current carbon intensity data available");
            }
            break;
            
        case 'cleanup':
            logMessage("Cleaning up old carbon intensity data...");
            
            // Delete records older than 90 days
            $stmt = $pdo->prepare('
                DELETE FROM external_carbon_intensity 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
            ');
            $stmt->execute();
            $deleted = $stmt->rowCount();
            
            logMessage("✓ Deleted {$deleted} old carbon intensity records");
            break;
            
        default:
            echo "Usage: php fetch_carbon_intensity.php [current|forecast|summary|cleanup]\n";
            echo "  current  - Fetch current carbon intensity (default)\n";
            echo "  forecast - Fetch today's forecast data\n";
            echo "  summary  - Display dashboard summary\n";
            echo "  cleanup  - Delete old data (>90 days)\n";
            exit(1);
    }
    
    logMessage("Carbon intensity fetch completed successfully");
    
} catch (Exception $e) {
    logMessage("ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
} catch (Error $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}