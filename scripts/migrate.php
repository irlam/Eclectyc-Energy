<?php
/**
 * eclectyc-energy/scripts/migrate.php
 * Database migration runner
 * Last updated: 06/11/2024 14:45:00
 */

// Check if running from CLI
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line.');
}

// Autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load environment
$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

echo "\n";
echo "===========================================\n";
echo "  Eclectyc Energy Database Migration\n";
echo "  " . date('d/m/Y H:i:s') . "\n";
echo "===========================================\n\n";

try {
    // Connect to MySQL server (without database)
    $dsn = sprintf(
        'mysql:host=%s;port=%s;charset=%s',
        $_ENV['DB_HOST'] ?? '127.0.0.1',
        $_ENV['DB_PORT'] ?? '3306',
        $_ENV['DB_CHARSET'] ?? 'utf8mb4'
    );
    
    $pdo = new PDO(
        $dsn,
        $_ENV['DB_USERNAME'] ?? 'root',
        $_ENV['DB_PASSWORD'] ?? ''
    );
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Run migration SQL file
    $migrationFile = dirname(__DIR__) . '/database/migrations/001_create_tables.sql';
    
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }
    
    echo "Running migration: 001_create_tables.sql\n";
    
    $sql = file_get_contents($migrationFile);
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    
    echo "✅ Migration completed successfully!\n\n";
    
    // Ask if user wants to seed data
    echo "Would you like to seed the database with sample data? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    
    if (trim($line) == 'y' || trim($line) == 'yes') {
        echo "\nRunning seed data...\n";
        
        $seedFile = dirname(__DIR__) . '/database/seeds/seed_data.sql';
        
        if (!file_exists($seedFile)) {
            throw new Exception("Seed file not found: $seedFile");
        }
        
        $sql = file_get_contents($seedFile);
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        echo "✅ Seed data inserted successfully!\n";
        echo "\nDefault admin credentials:\n";
        echo "  Email: admin@eclectyc.energy\n";
        echo "  Password: admin123\n";
        echo "\n⚠️  Remember to change the default password!\n";
    }
    
    echo "\nDatabase setup complete!\n";
    exit(0);
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}