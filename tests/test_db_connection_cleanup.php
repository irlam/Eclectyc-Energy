<?php
/**
 * eclectyc-energy/tests/test_db_connection_cleanup.php
 * Test to verify database connection cleanup is working properly
 * Last updated: 2025-11-12
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Database;

// Load environment
$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

echo "\n";
echo "===========================================\n";
echo "  Database Connection Cleanup Test\n";
echo "  " . date('d/m/Y H:i:s') . "\n";
echo "===========================================\n\n";

$testsPassed = 0;
$testsFailed = 0;

// Test 1: Verify connection can be established
echo "Test 1: Establishing database connection...\n";
try {
    $db = Database::getConnection();
    if ($db === null) {
        throw new Exception("Failed to get database connection");
    }
    
    // Verify connection is actually connected
    $stmt = $db->query("SELECT 1 as test");
    $result = $stmt->fetch();
    
    if ($result['test'] !== 1) {
        throw new Exception("Connection test query failed");
    }
    
    echo "✓ Database connection established successfully\n";
    $testsPassed++;
} catch (Exception $e) {
    echo "✗ Failed to establish connection: " . $e->getMessage() . "\n";
    $testsFailed++;
    exit(1);
}

// Test 2: Verify connection is closed
echo "\nTest 2: Closing database connection...\n";
try {
    Database::closeConnection();
    
    // Try to get connection again - should be null initially
    $reflection = new ReflectionClass('App\Config\Database');
    $property = $reflection->getProperty('connection');
    $property->setAccessible(true);
    $connection = $property->getValue();
    
    if ($connection !== null) {
        throw new Exception("Connection was not properly closed");
    }
    
    echo "✓ Database connection closed successfully\n";
    $testsPassed++;
} catch (Exception $e) {
    echo "✗ Failed to close connection: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 3: Verify connection can be re-established after closing
echo "\nTest 3: Re-establishing connection after close...\n";
try {
    $db = Database::getConnection();
    if ($db === null) {
        throw new Exception("Failed to reconnect to database");
    }
    
    // Verify connection works
    $stmt = $db->query("SELECT 1 as test");
    $result = $stmt->fetch();
    
    if ($result['test'] !== 1) {
        throw new Exception("Reconnection test query failed");
    }
    
    echo "✓ Database reconnection successful\n";
    $testsPassed++;
} catch (Exception $e) {
    echo "✗ Failed to reconnect: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 4: Check current connection count
echo "\nTest 4: Checking connection count...\n";
try {
    $db = Database::getConnection();
    if ($db === null) {
        throw new Exception("No database connection");
    }
    
    $stmt = $db->query("SHOW PROCESSLIST");
    $processes = $stmt->fetchAll();
    
    $user = $_ENV['DB_USERNAME'] ?? 'root';
    $userConnections = array_filter($processes, function($p) use ($user) {
        return $p['User'] === $user;
    });
    
    echo "  Current connections for user '$user': " . count($userConnections) . "\n";
    
    // We should have just 1 connection from this test script
    if (count($userConnections) > 5) {
        echo "⚠  Warning: More than 5 connections detected. This might indicate a leak.\n";
    } else {
        echo "✓ Connection count is within normal range\n";
        $testsPassed++;
    }
    
} catch (Exception $e) {
    echo "✗ Failed to check connections: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Test 5: Verify persistent connections are disabled
echo "\nTest 5: Verifying persistent connections are disabled...\n";
try {
    $db = Database::getConnection();
    if ($db === null) {
        throw new Exception("No database connection");
    }
    
    // Check if persistent attribute is false
    $isPersistent = $db->getAttribute(PDO::ATTR_PERSISTENT);
    
    if ($isPersistent) {
        throw new Exception("Persistent connections are still enabled!");
    }
    
    echo "✓ Persistent connections are disabled\n";
    $testsPassed++;
} catch (Exception $e) {
    echo "✗ Persistent connection check failed: " . $e->getMessage() . "\n";
    $testsFailed++;
}

// Final cleanup
Database::closeConnection();

// Summary
echo "\n";
echo "===========================================\n";
echo "  Test Summary\n";
echo "===========================================\n";
echo "Tests Passed: $testsPassed\n";
echo "Tests Failed: $testsFailed\n";
echo "\n";

if ($testsFailed > 0) {
    echo "❌ Some tests failed!\n\n";
    exit(1);
} else {
    echo "✅ All tests passed!\n\n";
    exit(0);
}
