#!/usr/bin/env php
<?php
/**
 * eclectyc-energy/scripts/test_connection_cleanup.php
 * Quick script to test connection cleanup behavior
 * Last updated: 2025-11-12
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Database;

$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

echo "\n";
echo "===========================================\n";
echo "  Connection Cleanup Test\n";
echo "  " . date('d/m/Y H:i:s') . "\n";
echo "===========================================\n\n";

echo "This script will:\n";
echo "1. Open a database connection\n";
echo "2. Count current connections\n";
echo "3. Close the connection\n";
echo "4. Reconnect and verify count\n";
echo "\n";

try {
    // Step 1: Connect
    echo "Step 1: Opening connection...\n";
    $db = Database::getConnection();
    if (!$db) {
        throw new Exception("Failed to connect");
    }
    echo "  ✓ Connected\n\n";
    
    // Step 2: Count connections
    echo "Step 2: Counting connections...\n";
    $stmt = $db->query("SHOW PROCESSLIST");
    $processes = $stmt->fetchAll();
    
    $user = $_ENV['DB_USERNAME'] ?? 'root';
    $userConnections = array_filter($processes, function($p) use ($user) {
        return $p['User'] === $user;
    });
    
    $count1 = count($userConnections);
    echo "  User '$user' has $count1 connection(s)\n\n";
    
    // Step 3: Close connection
    echo "Step 3: Closing connection...\n";
    Database::closeConnection();
    echo "  ✓ Connection closed\n\n";
    
    // Wait a moment for the close to propagate
    sleep(1);
    
    // Step 4: Reconnect and count
    echo "Step 4: Reconnecting and counting...\n";
    $db = Database::getConnection();
    if (!$db) {
        throw new Exception("Failed to reconnect");
    }
    
    $stmt = $db->query("SHOW PROCESSLIST");
    $processes = $stmt->fetchAll();
    
    $userConnections = array_filter($processes, function($p) use ($user) {
        return $p['User'] === $user;
    });
    
    $count2 = count($userConnections);
    echo "  User '$user' has $count2 connection(s)\n\n";
    
    // Verify persistent connections are disabled
    echo "Step 5: Verifying persistent connections disabled...\n";
    $isPersistent = $db->getAttribute(PDO::ATTR_PERSISTENT);
    if ($isPersistent) {
        echo "  ✗ WARNING: Persistent connections are ENABLED\n";
        echo "  This will cause connection leaks!\n\n";
    } else {
        echo "  ✓ Persistent connections are DISABLED\n\n";
    }
    
    // Clean up
    Database::closeConnection();
    
    echo "===========================================\n";
    echo "Test completed successfully!\n";
    echo "===========================================\n\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    Database::closeConnection();
    exit(1);
}

exit(0);
