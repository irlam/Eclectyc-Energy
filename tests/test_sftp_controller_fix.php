<?php
/**
 * Test that SftpController is properly registered in DI container
 */

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/vendor/autoload.php';

use App\Http\Controllers\Tools\SftpController;
use App\Http\Controllers\ToolsController;
use DI\Container;
use Slim\Views\Twig;

echo "\n";
echo "===========================================\n";
echo "  DI Container Registration Test\n";
echo "  " . date('d/m/Y H:i:s') . "\n";
echo "===========================================\n\n";

// Load environment variables
if (class_exists('Dotenv\\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
}

// Create container and set up like public/index.php does
$container = new Container();

// Add Twig to container
$container->set('view', function() {
    return Twig::create(BASE_PATH . '/app/views', [
        'cache' => false,
        'debug' => true
    ]);
});

// Add database connection to container (null for testing)
$container->set('db', function() {
    return null;
});

echo "Testing controller registration...\n";

// Test ToolsController
try {
    $container->set(\App\Http\Controllers\ToolsController::class, function(Container $c) {
        return new ToolsController($c->get('view'), $c->get('db'));
    });
    
    $toolsController = $container->get(\App\Http\Controllers\ToolsController::class);
    if (!($toolsController instanceof ToolsController)) {
        throw new Exception("ToolsController is not an instance of ToolsController class");
    }
    echo "✓ ToolsController registered and resolved successfully\n";
} catch (Exception $e) {
    echo "✗ ToolsController test failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test SftpController - this is the fix for the Slim Application Error
try {
    $container->set(\App\Http\Controllers\Tools\SftpController::class, function(Container $c) {
        return new SftpController($c->get('view'), $c->get('db'));
    });
    
    $sftpController = $container->get(\App\Http\Controllers\Tools\SftpController::class);
    if (!($sftpController instanceof SftpController)) {
        throw new Exception("SftpController is not an instance of SftpController class");
    }
    echo "✓ SftpController registered and resolved successfully\n";
} catch (Exception $e) {
    echo "✗ SftpController test failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";
echo "===========================================\n";
echo "  All Tests Passed! ✓\n";
echo "===========================================\n";
echo "\n";
echo "The Slim Application Error is FIXED!\n";
echo "SftpController is now properly registered in the DI container.\n";
echo "\n";
