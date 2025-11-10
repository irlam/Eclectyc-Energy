#!/usr/bin/env php
<?php
/**
 * eclectyc-energy/scripts/cleanup_db_connections.php
 * Monitor and cleanup stale database connections
 * 
 * This script helps prevent connection pool exhaustion by:
 * 1. Monitoring current connection count
 * 2. Identifying and killing stale connections (optional)
 * 3. Reporting connection statistics
 * 
 * Usage:
 *   php cleanup_db_connections.php              # Monitor only
 *   php cleanup_db_connections.php --kill-idle  # Kill idle connections
 * 
 * Last updated: 2025-11-10
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/London');

// Parse command line arguments
$options = getopt('h', ['help', 'kill-idle', 'max-idle:']);

if (isset($options['h']) || isset($options['help'])) {
    echo "\n";
    echo "Database Connection Cleanup Tool\n";
    echo "=================================\n\n";
    echo "Usage: php cleanup_db_connections.php [options]\n\n";
    echo "Options:\n";
    echo "  --kill-idle       Kill connections idle for more than max-idle seconds\n";
    echo "  --max-idle=N      Maximum idle time in seconds (default: 300)\n";
    echo "  -h, --help        Show this help message\n\n";
    echo "Examples:\n";
    echo "  php cleanup_db_connections.php\n";
    echo "  php cleanup_db_connections.php --kill-idle --max-idle=180\n\n";
    exit(0);
}

$killIdle = isset($options['kill-idle']);
$maxIdleTime = isset($options['max-idle']) ? (int)$options['max-idle'] : 300;

echo "\n";
echo "===========================================\n";
echo "  Database Connection Monitor\n";
echo "  " . date('d/m/Y H:i:s') . "\n";
echo "===========================================\n\n";

try {
    // Connect to database
    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $port = $_ENV['DB_PORT'] ?? '3306';
    $dbname = $_ENV['DB_DATABASE'] ?? 'energy_platform';
    $user = $_ENV['DB_USERNAME'] ?? 'root';
    $pass = $_ENV['DB_PASSWORD'] ?? '';

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbname);
    
    $pdo = new PDO(
        $dsn,
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Get current connections
    $stmt = $pdo->query("SHOW PROCESSLIST");
    $processes = $stmt->fetchAll();

    // Filter connections for current user
    $userConnections = array_filter($processes, function($p) use ($user) {
        return $p['User'] === $user;
    });

    echo "Total Connections: " . count($processes) . "\n";
    echo "Your User Connections: " . count($userConnections) . "\n";
    echo "\n";

    // Categorize connections
    $active = 0;
    $idle = 0;
    $sleeping = 0;
    $idleConnections = [];

    foreach ($userConnections as $conn) {
        $state = $conn['Command'] ?? '';
        $time = (int)($conn['Time'] ?? 0);
        
        if ($state === 'Sleep') {
            $sleeping++;
            if ($time > $maxIdleTime) {
                $idle++;
                $idleConnections[] = $conn;
            }
        } else {
            $active++;
        }
    }

    echo "Connection Breakdown:\n";
    echo "  Active:   $active\n";
    echo "  Sleeping: $sleeping\n";
    echo "  Idle (>$maxIdleTime"."s): $idle\n";
    echo "\n";

    // Show idle connections
    if (!empty($idleConnections)) {
        echo "Idle Connections:\n";
        echo str_repeat('-', 80) . "\n";
        printf("%-8s %-15s %-10s %-10s %s\n", "ID", "User", "DB", "Time(s)", "Info");
        echo str_repeat('-', 80) . "\n";
        
        foreach ($idleConnections as $conn) {
            printf(
                "%-8s %-15s %-10s %-10s %s\n",
                $conn['Id'],
                $conn['User'],
                $conn['db'] ?? 'NULL',
                $conn['Time'],
                substr($conn['Info'] ?? 'NULL', 0, 30)
            );
        }
        echo str_repeat('-', 80) . "\n";
        echo "\n";

        // Kill idle connections if requested
        if ($killIdle) {
            $killed = 0;
            foreach ($idleConnections as $conn) {
                try {
                    $pdo->exec("KILL {$conn['Id']}");
                    echo "Killed connection {$conn['Id']} (idle for {$conn['Time']}s)\n";
                    $killed++;
                } catch (PDOException $e) {
                    echo "Failed to kill connection {$conn['Id']}: {$e->getMessage()}\n";
                }
            }
            echo "\nKilled $killed idle connection(s)\n";
        } else {
            echo "To kill these idle connections, run with --kill-idle flag\n";
        }
    } else {
        echo "✓ No idle connections found (threshold: $maxIdleTime seconds)\n";
    }

    // Check max_user_connections setting
    try {
        $stmt = $pdo->query("SHOW VARIABLES LIKE 'max_user_connections'");
        $result = $stmt->fetch();
        if ($result) {
            $maxUserConn = $result['Value'];
            echo "\nDatabase Settings:\n";
            echo "  max_user_connections: $maxUserConn\n";
            
            if ($maxUserConn > 0) {
                $usage = count($userConnections);
                $percent = round(($usage / $maxUserConn) * 100, 1);
                echo "  Current usage: $usage / $maxUserConn ($percent%)\n";
                
                if ($percent > 80) {
                    echo "  ⚠️  WARNING: Using more than 80% of available connections!\n";
                } elseif ($percent > 60) {
                    echo "  ⚠️  CAUTION: Using more than 60% of available connections\n";
                } else {
                    echo "  ✓ Connection usage is healthy\n";
                }
            }
        }
    } catch (PDOException $e) {
        echo "\nCouldn't retrieve max_user_connections setting\n";
    }

    echo "\n";
    echo "===========================================\n";
    echo "Recommendations:\n";
    echo "===========================================\n";
    echo "1. Enable persistent connections (PDO::ATTR_PERSISTENT => true)\n";
    echo "2. Set connection timeout (PDO::ATTR_TIMEOUT => 30)\n";
    echo "3. Close connections explicitly in long-running scripts\n";
    echo "4. Schedule this script to run periodically (e.g., every 5 minutes)\n";
    echo "5. Monitor logs for connection errors\n";
    echo "\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

exit(0);
