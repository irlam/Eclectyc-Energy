<?php
/**
 * eclectyc-energy/scripts/run_data_quality_checks.php
 * CLI script to run data quality checks and detect missing data.
 * Last updated: 07/11/2025
 */

use App\Config\Database;
use App\Domain\Analytics\BaseloadAnalyzer;

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

function runDataQualityChecks(): int
{
    $args = getopt('d:m:v', ['date:', 'meter:', 'verbose', 'help']);
    
    if (isset($args['help'])) {
        printHelp();
        return 0;
    }
    
    $dateArgument = $args['d'] ?? $args['date'] ?? null;
    $meterId = $args['m'] ?? $args['meter'] ?? null;
    $verbose = isset($args['v']) || isset($args['verbose']);
    
    $targetDate = parseTargetDate($dateArgument);
    
    $pdo = Database::getConnection();
    if (!$pdo) {
        throw new RuntimeException('Failed to connect to database');
    }
    
    $analyzer = new BaseloadAnalyzer($pdo);
    
    // Get meters to check
    $meters = $meterId ? [['id' => $meterId]] : getActiveMeters($pdo);
    
    if (empty($meters)) {
        echo "No meters found to check.\n";
        return 0;
    }
    
    echo "Running data quality checks for " . $targetDate->format('Y-m-d') . "\n";
    echo "Checking " . count($meters) . " meters...\n\n";
    
    $totalIssues = 0;
    
    foreach ($meters as $meter) {
        $meterId = $meter['id'];
        
        try {
            $issues = $analyzer->detectDataQualityIssues($meterId, $targetDate);
            
            if (hasIssues($issues)) {
                $totalIssues++;
                
                if ($verbose) {
                    printIssues($meterId, $meter['mpan'] ?? 'N/A', $issues);
                }
                
                // Store issues in database
                storeIssues($pdo, $meterId, $targetDate, $issues);
            }
            
        } catch (Throwable $e) {
            fwrite(STDERR, "Error checking meter {$meterId}: {$e->getMessage()}\n");
        }
    }
    
    echo "\nData quality check complete.\n";
    echo "Meters with issues: {$totalIssues}\n";
    
    return 0;
}

function getActiveMeters(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, mpan FROM meters WHERE is_active = TRUE');
    return $stmt->fetchAll() ?: [];
}

function hasIssues(array $issues): bool
{
    return !empty($issues['missing_periods']) 
        || !empty($issues['anomalies'])
        || $issues['zero_readings'] > 0
        || $issues['negative_readings'] > 0
        || $issues['data_completeness'] < 100.0;
}

function printIssues(int $meterId, string $mpan, array $issues): void
{
    echo "Meter {$meterId} (MPAN: {$mpan}):\n";
    echo "  Data Completeness: {$issues['data_completeness']}%\n";
    
    if (!empty($issues['missing_periods'])) {
        echo "  Missing Periods: " . count($issues['missing_periods']) . "\n";
    }
    
    if ($issues['zero_readings'] > 0) {
        echo "  Zero Readings: {$issues['zero_readings']}\n";
    }
    
    if ($issues['negative_readings'] > 0) {
        echo "  Negative Readings: {$issues['negative_readings']}\n";
    }
    
    if (!empty($issues['anomalies'])) {
        echo "  Anomalies Detected: " . count($issues['anomalies']) . "\n";
        foreach ($issues['anomalies'] as $anomaly) {
            echo "    - Type: {$anomaly['type']}, Period: {$anomaly['period']}, Value: {$anomaly['value']}\n";
        }
    }
    
    echo "\n";
}

function storeIssues(PDO $pdo, int $meterId, DateTimeImmutable $date, array $issues): void
{
    try {
        // Store missing data issues
        if (!empty($issues['missing_periods'])) {
            $stmt = $pdo->prepare('
                INSERT INTO data_quality_issues 
                    (meter_id, issue_date, issue_type, severity, description, issue_data)
                VALUES (:meter_id, :issue_date, :issue_type, :severity, :description, :issue_data)
                ON DUPLICATE KEY UPDATE 
                    description = VALUES(description),
                    issue_data = VALUES(issue_data)
            ');
            
            $stmt->execute([
                'meter_id' => $meterId,
                'issue_date' => $date->format('Y-m-d'),
                'issue_type' => 'missing_data',
                'severity' => 'medium',
                'description' => 'Missing ' . count($issues['missing_periods']) . ' periods',
                'issue_data' => json_encode(['missing_periods' => $issues['missing_periods']]),
            ]);
        }
        
        // Store anomaly issues
        foreach ($issues['anomalies'] as $anomaly) {
            $stmt = $pdo->prepare('
                INSERT INTO data_quality_issues 
                    (meter_id, issue_date, issue_type, severity, description, issue_data)
                VALUES (:meter_id, :issue_date, :issue_type, :severity, :description, :issue_data)
            ');
            
            $stmt->execute([
                'meter_id' => $meterId,
                'issue_date' => $date->format('Y-m-d'),
                'issue_type' => 'anomaly',
                'severity' => $anomaly['type'] === 'negative_value' ? 'high' : 'low',
                'description' => ucfirst($anomaly['type']) . ' detected',
                'issue_data' => json_encode($anomaly),
            ]);
        }
        
    } catch (PDOException $e) {
        fwrite(STDERR, "Failed to store issues: {$e->getMessage()}\n");
    }
}

function parseTargetDate(?string $dateString): DateTimeImmutable
{
    if ($dateString === null) {
        return new DateTimeImmutable('yesterday');
    }
    
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $dateString);
    if ($parsed === false) {
        throw new RuntimeException('Invalid date format. Use YYYY-MM-DD.');
    }
    
    return $parsed;
}

function printHelp(): void
{
    echo "Data Quality Check Script\n";
    echo "=========================\n\n";
    echo "Usage: php run_data_quality_checks.php [options]\n\n";
    echo "Options:\n";
    echo "  -d, --date <date>    Target date (YYYY-MM-DD, default: yesterday)\n";
    echo "  -m, --meter <id>     Check specific meter ID (default: all active)\n";
    echo "  -v, --verbose        Verbose output\n";
    echo "  --help               Show this help message\n\n";
    echo "Examples:\n";
    echo "  php run_data_quality_checks.php --verbose\n";
    echo "  php run_data_quality_checks.php --date 2025-11-06 --meter 123\n";
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $exitCode = runDataQualityChecks();
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n");
        $exitCode = 1;
    }
    
    exit($exitCode);
}
