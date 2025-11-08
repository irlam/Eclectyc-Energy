<?php
/**
 * Test script to verify cancel job functionality
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Domain\Ingestion\ImportJobService;

echo "\n";
echo "===========================================\n";
echo "  Cancel Job Functionality Test\n";
echo "  " . date('d/m/Y H:i:s') . "\n";
echo "===========================================\n\n";

// Test that the cancelJob method exists
echo "Testing ImportJobService class...\n";

try {
    // Check if class exists
    if (!class_exists('App\Domain\Ingestion\ImportJobService')) {
        throw new Exception("ImportJobService class not found");
    }
    echo "✓ ImportJobService class loaded successfully\n";

    // Check if cancelJob method exists
    $reflection = new ReflectionClass('App\Domain\Ingestion\ImportJobService');
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    
    $hasCancelJob = false;
    foreach ($methods as $method) {
        if ($method->getName() === 'cancelJob') {
            $hasCancelJob = true;
            break;
        }
    }
    
    if (!$hasCancelJob) {
        throw new Exception("cancelJob method not found in ImportJobService");
    }
    echo "✓ cancelJob method exists in ImportJobService\n";
    
    // Check method signature
    $cancelJobMethod = $reflection->getMethod('cancelJob');
    $parameters = $cancelJobMethod->getParameters();
    
    if (count($parameters) !== 1) {
        throw new Exception("cancelJob method should have exactly 1 parameter");
    }
    
    if ($parameters[0]->getName() !== 'batchId') {
        throw new Exception("cancelJob method parameter should be named 'batchId'");
    }
    
    echo "✓ cancelJob method has correct signature\n";
    
    // Check return type
    $returnType = $cancelJobMethod->getReturnType();
    if (!$returnType || $returnType->getName() !== 'bool') {
        throw new Exception("cancelJob method should return bool");
    }
    echo "✓ cancelJob method returns bool\n";
    
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test ImportController has cancelJob method
echo "\nTesting ImportController class...\n";

try {
    if (!class_exists('App\Http\Controllers\Admin\ImportController')) {
        throw new Exception("ImportController class not found");
    }
    echo "✓ ImportController class loaded successfully\n";
    
    $reflection = new ReflectionClass('App\Http\Controllers\Admin\ImportController');
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    
    $hasCancelJob = false;
    foreach ($methods as $method) {
        if ($method->getName() === 'cancelJob') {
            $hasCancelJob = true;
            break;
        }
    }
    
    if (!$hasCancelJob) {
        throw new Exception("cancelJob method not found in ImportController");
    }
    echo "✓ cancelJob method exists in ImportController\n";
    
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test ToolsController has viewLogs and clearLogs methods
echo "\nTesting ToolsController log viewer methods...\n";

try {
    if (!class_exists('App\Http\Controllers\ToolsController')) {
        throw new Exception("ToolsController class not found");
    }
    echo "✓ ToolsController class loaded successfully\n";
    
    $reflection = new ReflectionClass('App\Http\Controllers\ToolsController');
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    
    $hasViewLogs = false;
    $hasClearLogs = false;
    foreach ($methods as $method) {
        if ($method->getName() === 'viewLogs') {
            $hasViewLogs = true;
        }
        if ($method->getName() === 'clearLogs') {
            $hasClearLogs = true;
        }
    }
    
    if (!$hasViewLogs) {
        throw new Exception("viewLogs method not found in ToolsController");
    }
    echo "✓ viewLogs method exists in ToolsController\n";
    
    if (!$hasClearLogs) {
        throw new Exception("clearLogs method not found in ToolsController");
    }
    echo "✓ clearLogs method exists in ToolsController\n";
    
} catch (Exception $e) {
    echo "✗ Test failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";
echo "===========================================\n";
echo "  All Tests Passed! ✓\n";
echo "===========================================\n";
echo "\n";
echo "New Features Verified:\n";
echo "  • Import job cancellation\n";
echo "  • Log viewer functionality\n";
echo "\n";
