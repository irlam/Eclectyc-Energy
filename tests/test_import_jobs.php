<?php
/**
 * eclectyc-energy/tests/test_import_jobs.php
 * Simple integration test for import job functionality
 * Last updated: 2025-11-07
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Domain\Ingestion\ImportJobService;

// Load environment
$dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

echo "\n";
echo "===========================================\n";
echo "  Import Job Service Test\n";
echo "  " . date('d/m/Y H:i:s') . "\n";
echo "===========================================\n\n";

// This is a dry-run test that doesn't require a database
echo "Testing ImportJobService class loading...\n";

try {
    // Test 1: Check if class exists
    if (!class_exists('App\Domain\Ingestion\ImportJobService')) {
        throw new Exception("ImportJobService class not found");
    }
    echo "✓ ImportJobService class loaded successfully\n";

    // Test 2: Check if we can instantiate with a mock PDO
    // Note: This won't work without a real database, but we can check the class definition
    $reflection = new ReflectionClass('App\Domain\Ingestion\ImportJobService');
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    
    $expectedMethods = [
        'createJob',
        'updateStatus',
        'updateProgress',
        'completeJob',
        'getJob',
        'getRecentJobs',
        'getQueuedJobs',
        'cleanupOldJobs'
    ];
    
    foreach ($expectedMethods as $methodName) {
        $found = false;
        foreach ($methods as $method) {
            if ($method->getName() === $methodName) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new Exception("Method $methodName not found in ImportJobService");
        }
    }
    echo "✓ All expected methods found in ImportJobService\n";

    // Test 3: Check controller classes
    if (!class_exists('App\Http\Controllers\Api\ImportJobController')) {
        throw new Exception("ImportJobController class not found");
    }
    echo "✓ ImportJobController class loaded successfully\n";

    // Test 4: Check if process_import_jobs.php exists
    $scriptPath = dirname(__DIR__) . '/scripts/process_import_jobs.php';
    if (!file_exists($scriptPath)) {
        throw new Exception("process_import_jobs.php script not found");
    }
    echo "✓ Background worker script exists\n";

    // Test 5: Check syntax of the worker script
    $output = [];
    $return = 0;
    exec('php -l ' . escapeshellarg($scriptPath) . ' 2>&1', $output, $return);
    if ($return !== 0) {
        throw new Exception("Syntax error in process_import_jobs.php: " . implode("\n", $output));
    }
    echo "✓ Background worker script has valid syntax\n";

    // Test 6: Check if migration file exists
    $migrationPath = dirname(__DIR__) . '/database/migrations/004_create_import_jobs_table.sql';
    if (!file_exists($migrationPath)) {
        throw new Exception("Import jobs migration not found");
    }
    echo "✓ Database migration file exists\n";

    // Test 7: Check if storage directory exists
    $storageDir = dirname(__DIR__) . '/storage/imports';
    if (!is_dir($storageDir)) {
        throw new Exception("Storage directory not found");
    }
    echo "✓ Storage directory exists\n";

    // Test 8: Check if storage directory is writable
    if (!is_writable($storageDir)) {
        throw new Exception("Storage directory is not writable");
    }
    echo "✓ Storage directory is writable\n";

    // Test 9: Check if templates exist
    $templates = [
        'app/views/admin/import_status.twig',
        'app/views/admin/import_jobs.twig',
    ];
    
    foreach ($templates as $template) {
        $templatePath = dirname(__DIR__) . '/' . $template;
        if (!file_exists($templatePath)) {
            throw new Exception("Template not found: $template");
        }
    }
    echo "✓ All required templates exist\n";

    echo "\n";
    echo "===========================================\n";
    echo "  All Tests Passed ✓\n";
    echo "===========================================\n\n";
    
    echo "Next steps:\n";
    echo "1. Run database migrations: php scripts/migrate.php\n";
    echo "2. Configure database credentials in .env\n";
    echo "3. Start the background worker: php scripts/process_import_jobs.php\n";
    echo "4. Access the web interface at /admin/imports\n\n";
    
    exit(0);

} catch (Exception $e) {
    echo "\n✗ Test failed: " . $e->getMessage() . "\n\n";
    exit(1);
}
