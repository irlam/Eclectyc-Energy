<?php
/**
 * eclectyc-energy/scripts/seed.php
 * Database seeder runner (separate from migration)
 * Last updated: 06/11/2025 20:50:00
 */

// Check if running from CLI
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line.');
}

// Autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load environment
$dotenvClass = 'Dotenv\\Dotenv';
if (class_exists($dotenvClass)) {
    $dotenv = $dotenvClass::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
}

echo "\n";
echo "===========================================\n";
echo "  Eclectyc Energy Database Seeder\n";
echo "  " . date('d/m/Y H:i:s') . "\n";
echo "===========================================\n\n";

echo "⚠️  Warning: This will insert sample data into your database.\n";
echo "Continue? (y/n): ";

$handle = fopen("php://stdin", "r");
$line = fgets($handle);

if (trim($line) != 'y' && trim($line) != 'yes') {
    echo "Seeding cancelled.\n";
    exit(0);
}

try {
    // Connect to database
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $_ENV['DB_HOST'] ?? '127.0.0.1',
        $_ENV['DB_PORT'] ?? '3306',
        $_ENV['DB_DATABASE'] ?? 'energy_platform',
        $_ENV['DB_CHARSET'] ?? 'utf8mb4'
    );
    
    $pdo = new PDO(
        $dsn,
        $_ENV['DB_USERNAME'] ?? 'root',
        $_ENV['DB_PASSWORD'] ?? ''
    );
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Run seed SQL file
    $seedFile = dirname(__DIR__) . '/database/seeds/seed_data.sql';
    
    if (!file_exists($seedFile)) {
        throw new Exception("Seed file not found: $seedFile");
    }
    
    echo "Running seed file: seed_data.sql\n";
    
    $sql = file_get_contents($seedFile);
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $count = 0;
    
    foreach ($statements as $statement) {
        if (!empty($statement) && stripos($statement, 'USE ') !== 0) {
            $pdo->exec($statement);
            $count++;
        }
    }
    
    echo "✅ Executed $count statements successfully!\n\n";
    
    echo "Sample data inserted:\n";
    echo "  - Admin user (admin@eclectyc.energy / admin123)\n";
    echo "  - 5 Energy suppliers\n";
    echo "  - 12 UK regions\n";
    echo "  - 1 Company (Eclectyc Energy Ltd)\n";
    echo "  - 3 Sites\n";
    echo "  - 4 Meters\n";
    echo "  - 4 Tariffs\n";
    echo "  - Sample meter readings\n";
    echo "  - Aggregation tables pre-populated (daily/weekly/monthly/annual)\n";
    echo "  - System settings\n\n";
    
    echo "⚠️  Remember to change the default admin password!\n\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}