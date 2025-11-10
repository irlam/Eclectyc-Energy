#!/usr/bin/env php
<?php
/**
 * eclectyc-energy/scripts/cleanup_logs.php
 * Automated log cleanup and archival script
 * 
 * Purpose:
 * - Clean up old cron_logs entries (older than 30 days)
 * - Archive old audit_logs entries (older than 30 days)
 * - Rotate large log files to prevent disk space issues
 * - Create backups before deletion
 * 
 * Usage: php scripts/cleanup_logs.php [--dry-run] [--verbose] [--retention-days=30]
 * 
 * Last updated: 10/11/2025
 */

use App\Config\Database;

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script can only be run from command line.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load environment
$dotenvClass = 'Dotenv\\Dotenv';
if (class_exists($dotenvClass)) {
    $dotenv = $dotenvClass::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
}

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/London');

// Parse command line arguments
$dryRun = in_array('--dry-run', $argv);
$verbose = in_array('--verbose', $argv) || in_array('-v', $argv);
$retentionDays = 30;

foreach ($argv as $arg) {
    if (strpos($arg, '--retention-days=') === 0) {
        $retentionDays = (int) substr($arg, strlen('--retention-days='));
    }
}

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║         Eclectyc Energy - Log Cleanup & Archival            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Retention period: {$retentionDays} days\n";
echo "Mode: " . ($dryRun ? "DRY RUN (no changes will be made)" : "LIVE") . "\n";
echo "\n";

$pdo = Database::getConnection();
if (!$pdo) {
    echo "❌ Failed to connect to database\n";
    exit(1);
}

$cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
$backupDir = dirname(__DIR__) . '/logs/backups';

// Ensure backup directory exists
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// ===== Clean up cron_logs =====
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  Cleaning up cron_logs table                                 ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

try {
    // Check if table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'cron_logs'")->fetch();
    
    if ($tableCheck) {
        // Count old records
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cron_logs WHERE created_at < ?");
        $stmt->execute([$cutoffDate]);
        $oldCronLogs = (int) $stmt->fetchColumn();
        
        echo "Found {$oldCronLogs} cron log entries older than {$retentionDays} days\n";
        
        if ($oldCronLogs > 0) {
            if (!$dryRun) {
                // Create backup
                $backupFile = $backupDir . '/cron_logs_backup_' . date('Y-m-d_H-i-s') . '.json';
                echo "Creating backup at: {$backupFile}\n";
                
                $stmt = $pdo->prepare("SELECT * FROM cron_logs WHERE created_at < ? ORDER BY created_at DESC");
                $stmt->execute([$cutoffDate]);
                $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                file_put_contents($backupFile, json_encode($records, JSON_PRETTY_PRINT));
                echo "✅ Backup created successfully\n";
                
                // Delete old records
                $stmt = $pdo->prepare("DELETE FROM cron_logs WHERE created_at < ?");
                $stmt->execute([$cutoffDate]);
                $deleted = $stmt->rowCount();
                echo "✅ Deleted {$deleted} old cron log entries\n";
            } else {
                echo "ℹ️  Would delete {$oldCronLogs} entries (dry run mode)\n";
            }
        } else {
            echo "✅ No old cron logs to clean up\n";
        }
    } else {
        echo "ℹ️  cron_logs table does not exist yet\n";
    }
} catch (Exception $e) {
    echo "❌ Error cleaning cron_logs: " . $e->getMessage() . "\n";
}

echo "\n";

// ===== Archive old audit_logs =====
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  Archiving old audit_logs entries                            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

try {
    // Count old records (keep more audit logs - archive to backup only)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE created_at < ?");
    $stmt->execute([$cutoffDate]);
    $oldAuditLogs = (int) $stmt->fetchColumn();
    
    echo "Found {$oldAuditLogs} audit log entries older than {$retentionDays} days\n";
    
    if ($oldAuditLogs > 0 && $oldAuditLogs > 1000) { // Only archive if more than 1000 old records
        if (!$dryRun) {
            // Create backup
            $backupFile = $backupDir . '/audit_logs_archive_' . date('Y-m-d_H-i-s') . '.json';
            echo "Creating archive at: {$backupFile}\n";
            
            $stmt = $pdo->prepare("SELECT * FROM audit_logs WHERE created_at < ? ORDER BY created_at DESC LIMIT 10000");
            $stmt->execute([$cutoffDate]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            file_put_contents($backupFile, json_encode($records, JSON_PRETTY_PRINT));
            echo "✅ Archive created successfully\n";
            
            // Delete archived records (only if > 5000 old records to prevent table from being too small)
            if ($oldAuditLogs > 5000) {
                $stmt = $pdo->prepare("DELETE FROM audit_logs WHERE created_at < ? LIMIT 5000");
                $stmt->execute([$cutoffDate]);
                $deleted = $stmt->rowCount();
                echo "✅ Deleted {$deleted} old audit log entries\n";
            } else {
                echo "ℹ️  Keeping audit logs (not enough old records to warrant deletion)\n";
            }
        } else {
            echo "ℹ️  Would archive and potentially delete old entries (dry run mode)\n";
        }
    } else {
        echo "✅ No significant old audit logs to archive\n";
    }
} catch (Exception $e) {
    echo "❌ Error archiving audit_logs: " . $e->getMessage() . "\n";
}

echo "\n";

// ===== Rotate large log files =====
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  Rotating large log files                                    ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

$logDir = dirname(__DIR__) . '/logs';
$maxLogSize = 10 * 1024 * 1024; // 10 MB

$logFiles = [
    $logDir . '/php-error.log',
    $logDir . '/app.log',
];

foreach ($logFiles as $logFile) {
    if (file_exists($logFile)) {
        $fileSize = filesize($logFile);
        $fileSizeMB = round($fileSize / (1024 * 1024), 2);
        
        echo "Checking: " . basename($logFile) . " ({$fileSizeMB} MB)\n";
        
        if ($fileSize > $maxLogSize) {
            if (!$dryRun) {
                $rotatedFile = $logFile . '.' . date('Y-m-d_H-i-s');
                rename($logFile, $rotatedFile);
                touch($logFile);
                chmod($logFile, 0644);
                echo "  ✅ Rotated to: " . basename($rotatedFile) . "\n";
                
                // Compress old rotated file
                if (function_exists('gzopen')) {
                    $gz = gzopen($rotatedFile . '.gz', 'wb9');
                    gzwrite($gz, file_get_contents($rotatedFile));
                    gzclose($gz);
                    unlink($rotatedFile);
                    echo "  ✅ Compressed to: " . basename($rotatedFile . '.gz') . "\n";
                }
            } else {
                echo "  ℹ️  Would rotate this file (dry run mode)\n";
            }
        } else {
            echo "  ✅ File size OK\n";
        }
    } else {
        echo "Skipping: " . basename($logFile) . " (does not exist)\n";
    }
}

echo "\n";

// ===== Clean up old backup files (keep only last 10 backups) =====
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  Cleaning old backup files                                   ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

if (is_dir($backupDir)) {
    $backups = glob($backupDir . '/*.{json,gz}', GLOB_BRACE);
    if (count($backups) > 10) {
        // Sort by modification time, oldest first
        usort($backups, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $toDelete = array_slice($backups, 0, count($backups) - 10);
        echo "Found " . count($toDelete) . " old backup files to delete\n";
        
        if (!$dryRun) {
            foreach ($toDelete as $file) {
                unlink($file);
                if ($verbose) {
                    echo "  Deleted: " . basename($file) . "\n";
                }
            }
            echo "✅ Deleted " . count($toDelete) . " old backup files\n";
        } else {
            echo "ℹ️  Would delete " . count($toDelete) . " old backup files (dry run mode)\n";
        }
    } else {
        echo "✅ No old backup files to clean up\n";
    }
}

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║  Log Cleanup Complete                                        ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

exit(0);
