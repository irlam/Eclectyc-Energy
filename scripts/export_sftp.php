<?php
/**
 * eclectyc-energy/scripts/export_sftp.php
 * Export data via SFTP to external systems
 * Last updated: 06/11/2024 14:45:00
 */

use App\Config\Database;
use League\Csv\Writer;
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;

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
$args = getopt('t:d:f:h', ['type:', 'date:', 'format:', 'help']);

// Show help
if (isset($args['h']) || isset($args['help'])) {
    echo "\n";
    echo "Eclectyc Energy SFTP Export\n";
    echo "===========================\n\n";
    echo "Usage: php export_sftp.php -t <type> [-d <date>] [-f <format>]\n\n";
    echo "Options:\n";
    echo "  -t, --type     Export type: daily, monthly, meters (required)\n";
    echo "  -d, --date     Date for export (default: yesterday)\n";
    echo "  -f, --format   Export format: csv, json (default: csv)\n";
    echo "  -h, --help     Show this help message\n\n";
    echo "Example:\n";
    echo "  php export_sftp.php -t daily -d 2024-11-05 -f csv\n\n";
    exit(0);
}

// Validate arguments
$exportType = $args['t'] ?? $args['type'] ?? null;
$exportDate = $args['d'] ?? $args['date'] ?? date('Y-m-d', strtotime('-1 day'));
$exportFormat = $args['f'] ?? $args['format'] ?? 'csv';

if (!$exportType) {
    echo "Error: Export type is required. Use -h for help.\n";
    exit(1);
}

if (!in_array($exportType, ['daily', 'monthly', 'meters'])) {
    echo "Error: Invalid export type. Must be 'daily', 'monthly', or 'meters'.\n";
    exit(1);
}

if (!in_array($exportFormat, ['csv', 'json'])) {
    echo "Error: Invalid export format. Must be 'csv' or 'json'.\n";
    exit(1);
}

echo "\n";
echo "===========================================\n";
echo "  Eclectyc Energy SFTP Export\n";
echo "  " . date('d/m/Y H:i:s') . "\n";
echo "===========================================\n\n";
echo "Type: $exportType\n";
echo "Date: $exportDate\n";
echo "Format: $exportFormat\n\n";

try {
    // Connect to database
    $db = Database::getConnection();
    if (!$db) {
        throw new Exception("Failed to connect to database");
    }
    
    // Prepare export data based on type
    $data = [];
    $filename = '';
    
    switch ($exportType) {
        case 'daily':
            // Export daily aggregations
            $query = $db->prepare("
                SELECT 
                    m.mpan,
                    s.name as site_name,
                    da.date,
                    da.total_consumption,
                    da.peak_consumption,
                    da.off_peak_consumption,
                    da.reading_count
                FROM daily_aggregations da
                JOIN meters m ON da.meter_id = m.id
                JOIN sites s ON m.site_id = s.id
                WHERE da.date = ?
                ORDER BY m.mpan
            ");
            
            $query->execute([$exportDate]);
            $data = $query->fetchAll();
            $filename = "daily_export_" . str_replace('-', '', $exportDate);
            break;
            
        case 'monthly':
            // Export monthly summary
            $startDate = date('Y-m-01', strtotime($exportDate));
            $endDate = date('Y-m-t', strtotime($exportDate));
            
            $query = $db->prepare("
                SELECT 
                    m.mpan,
                    s.name as site_name,
                    DATE_FORMAT(da.date, '%Y-%m') as month,
                    SUM(da.total_consumption) as total_consumption,
                    SUM(da.peak_consumption) as peak_consumption,
                    SUM(da.off_peak_consumption) as off_peak_consumption,
                    AVG(da.total_consumption) as avg_daily_consumption,
                    COUNT(DISTINCT da.date) as days_with_data
                FROM daily_aggregations da
                JOIN meters m ON da.meter_id = m.id
                JOIN sites s ON m.site_id = s.id
                WHERE da.date BETWEEN ? AND ?
                GROUP BY m.id, month
                ORDER BY m.mpan
            ");
            
            $query->execute([$startDate, $endDate]);
            $data = $query->fetchAll();
            $filename = "monthly_export_" . date('Ym', strtotime($exportDate));
            break;
            
        case 'meters':
            // Export meter list
            $query = $db->query("
                SELECT 
                    m.mpan,
                    m.serial_number,
                    m.meter_type,
                    m.is_smart_meter,
                    m.is_half_hourly,
                    s.name as site_name,
                    s.address as site_address,
                    s.postcode,
                    c.name as company_name,
                    sup.name as supplier_name
                FROM meters m
                JOIN sites s ON m.site_id = s.id
                JOIN companies c ON s.company_id = c.id
                LEFT JOIN suppliers sup ON m.supplier_id = sup.id
                WHERE m.is_active = TRUE
                ORDER BY m.mpan
            ");
            
            $data = $query->fetchAll();
            $filename = "meters_export_" . date('Ymd');
            break;
    }
    
    echo "Found " . count($data) . " records to export\n\n";
    
    if (empty($data)) {
        echo "No data to export.\n";
        exit(0);
    }
    
    // Create export file
    $exportDir = dirname(__DIR__) . '/exports';
    if (!is_dir($exportDir)) {
        mkdir($exportDir, 0777, true);
    }
    
    $filepath = $exportDir . '/' . $filename . '.' . $exportFormat;
    
    if ($exportFormat === 'csv') {
        // Export as CSV
        $csv = Writer::createFromPath($filepath, 'w+');
        $csv->insertOne(array_keys($data[0]));
        $csv->insertAll($data);
        
        echo "Created CSV file: $filepath\n";
    } else {
        // Export as JSON
        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));
        echo "Created JSON file: $filepath\n";
    }
    
    $filesize = filesize($filepath);
    echo "File size: " . number_format($filesize / 1024, 2) . " KB\n\n";
    
    // SFTP upload (if configured)
    $sftpHost = $_ENV['SFTP_HOST'] ?? null;
    $sftpUser = $_ENV['SFTP_USERNAME'] ?? null;
    $sftpPassword = $_ENV['SFTP_PASSWORD'] ?? null;
    $sftpPrivateKey = $_ENV['SFTP_PRIVATE_KEY'] ?? null;
    $sftpPort = (int) ($_ENV['SFTP_PORT'] ?? 22);
    $sftpPath = $_ENV['SFTP_PATH'] ?? '';
    $sftpTimeout = (int) ($_ENV['SFTP_TIMEOUT'] ?? 15);

    $hasCredentials = $sftpHost && $sftpUser && ($sftpPassword || $sftpPrivateKey);

    if ($hasCredentials) {
        echo "Uploading to SFTP server...\n";

        try {
            $sftp = new SFTP($sftpHost, $sftpPort, $sftpTimeout);

            if (!$sftp->isConnected()) {
                throw new Exception('Unable to establish SFTP connection.');
            }

            $credential = $sftpPassword;

            if ($sftpPrivateKey) {
                $keyMaterial = is_file($sftpPrivateKey)
                    ? file_get_contents($sftpPrivateKey)
                    : $sftpPrivateKey;

                if ($keyMaterial === false) {
                    throw new Exception('Unable to read SFTP private key material.');
                }

                $credential = PublicKeyLoader::load($keyMaterial, $_ENV['SFTP_PASSPHRASE'] ?? false);
            }

            if (!$sftp->login($sftpUser, $credential)) {
                throw new Exception('SFTP authentication failed. Check username/password or key.');
            }

            $remoteDirectory = rtrim($sftpPath, '/') . '/';
            $remotePath = $remoteDirectory . basename($filepath);

            if (!empty($sftpPath) && !$sftp->is_dir($sftpPath)) {
                $sftp->mkdir($sftpPath, -1, true);
            }

            if (!$sftp->put($remotePath, $filepath, SFTP::SOURCE_LOCAL_FILE)) {
                throw new Exception('SFTP upload failed.');
            }

            echo "Uploaded to SFTP: $remotePath\n\n";
        } catch (Exception $sftpException) {
            echo "SFTP upload failed: " . $sftpException->getMessage() . "\n";
            echo "File saved locally only.\n\n";
        }
    } else {
        echo "SFTP not fully configured. File saved locally only.\n";
    }
    
    // Log export to database
    $exportQuery = $db->prepare("
        INSERT INTO exports 
        (export_type, export_format, file_name, file_path, file_size, status, completed_at)
        VALUES (?, ?, ?, ?, ?, 'completed', NOW())
    ");
    
    $exportQuery->execute([
        $exportType,
        $exportFormat,
        basename($filepath),
        $filepath,
        $filesize
    ]);
    
    echo "Export logged to database.\n";
    echo "Export complete!\n\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage() . "\n";
    exit(1);
}