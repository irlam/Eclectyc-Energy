<?php
/**
 * eclectyc-energy/scripts/migrate.php
 * Database migration runner
 * Last updated: 06/11/2024 14:45:00
 */

// Determine context (CLI vs web)
$isCli = php_sapi_name() === 'cli';

// Autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load environment
$dotenvClass = '\\Dotenv\\Dotenv';
if (class_exists($dotenvClass)) {
    $dotenv = $dotenvClass::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
} else {
    $envFile = dirname(__DIR__) . '/.env';
    if (is_readable($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $value = trim($value, "\"' ");
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

// Protect web execution behind secret key
if (!$isCli) {
    $expectedKey = $_ENV['MIGRATION_KEY'] ?? '';
    $providedKey = $_GET['key'] ?? ($_SERVER['HTTP_X_MIGRATION_KEY'] ?? '');
    $valid = $expectedKey !== '' && $providedKey !== '' && hash_equals($expectedKey, $providedKey);
    if (!$valid) {
        http_response_code(403);
        echo 'Forbidden';
        exit(1);
    }
}

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
    $shouldSeed = false;
    if ($isCli) {
        echo "Would you like to seed the database with sample data? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        $shouldSeed = trim($line) == 'y' || trim($line) == 'yes';
    } else {
        $seedParam = $_GET['seed'] ?? '0';
        $shouldSeed = filter_var($seedParam, FILTER_VALIDATE_BOOLEAN);
    }
    
    if ($shouldSeed) {
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