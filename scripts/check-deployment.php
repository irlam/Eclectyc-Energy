#!/usr/bin/env php
<?php
/**
 * Deployment Structure Checker
 * 
 * This script checks if the project is deployed correctly and identifies
 * common deployment issues like the public/public path duplication.
 * 
 * Usage:
 *   php scripts/check-deployment.php
 * 
 * Or make it executable:
 *   chmod +x scripts/check-deployment.php
 *   ./scripts/check-deployment.php
 */

// ANSI color codes for terminal output
define('COLOR_RESET', "\033[0m");
define('COLOR_RED', "\033[0;31m");
define('COLOR_GREEN', "\033[0;32m");
define('COLOR_YELLOW', "\033[1;33m");
define('COLOR_BLUE', "\033[0;34m");
define('COLOR_BOLD', "\033[1m");

function printHeader($text) {
    echo "\n" . COLOR_BOLD . COLOR_BLUE . "=== " . $text . " ===" . COLOR_RESET . "\n\n";
}

function printSuccess($text) {
    echo COLOR_GREEN . "✓ " . $text . COLOR_RESET . "\n";
}

function printError($text) {
    echo COLOR_RED . "✗ " . $text . COLOR_RESET . "\n";
}

function printWarning($text) {
    echo COLOR_YELLOW . "⚠ " . $text . COLOR_RESET . "\n";
}

function printInfo($text) {
    echo "  " . $text . "\n";
}

// Get the project root (parent of this script's directory)
$scriptDir = dirname(__FILE__);
$projectRoot = dirname($scriptDir);

printHeader("Eclectyc Energy Deployment Structure Checker");

echo "Project Root: " . COLOR_BOLD . $projectRoot . COLOR_RESET . "\n";
echo "Current Directory: " . COLOR_BOLD . getcwd() . COLOR_RESET . "\n";

$errors = 0;
$warnings = 0;

// Check 1: Are we in the right directory?
printHeader("Check 1: Directory Structure");

$requiredDirs = ['app', 'public', 'database', 'storage'];
$requiredFiles = ['composer.json', 'public/index.php'];

foreach ($requiredDirs as $dir) {
    $path = $projectRoot . '/' . $dir;
    if (is_dir($path)) {
        printSuccess("Directory exists: $dir/");
    } else {
        printError("Missing directory: $dir/");
        $errors++;
    }
}

foreach ($requiredFiles as $file) {
    $path = $projectRoot . '/' . $file;
    if (file_exists($path)) {
        printSuccess("File exists: $file");
    } else {
        printError("Missing file: $file");
        $errors++;
    }
}

// Check 2: Public/public duplication
printHeader("Check 2: Path Duplication Detection");

$publicPublic = $projectRoot . '/public/public';
if (is_dir($publicPublic)) {
    printError("CRITICAL: Detected public/public/ directory!");
    printInfo("This indicates files were uploaded to wrong location.");
    printInfo("See: docs/DEPLOYMENT_PATH_ISSUE.md for fix instructions.");
    $errors++;
} else {
    printSuccess("No public/public/ duplication detected");
}

// Check for common misplaced files
$misplacedFiles = [
    'public/app' => 'app/ directory inside public/',
    'public/vendor' => 'vendor/ directory inside public/',
    'public/composer.json' => 'composer.json inside public/'
];

foreach ($misplacedFiles as $path => $description) {
    $fullPath = $projectRoot . '/' . $path;
    if (file_exists($fullPath)) {
        printWarning("Found misplaced: $description");
        printInfo("File/directory: $path");
        $warnings++;
    }
}

// Check 3: Vendor directory
printHeader("Check 3: Dependencies");

$vendorPath = $projectRoot . '/vendor';
if (is_dir($vendorPath)) {
    printSuccess("Vendor directory exists");
    
    $autoloadPath = $vendorPath . '/autoload.php';
    if (file_exists($autoloadPath)) {
        printSuccess("Composer autoload.php exists");
    } else {
        printError("Missing vendor/autoload.php - run 'composer install'");
        $errors++;
    }
} else {
    printError("Vendor directory missing - run 'composer install'");
    $errors++;
}

// Check 4: .env file
printHeader("Check 4: Configuration");

$envPath = $projectRoot . '/.env';
if (file_exists($envPath)) {
    printSuccess(".env file exists");
    
    // Check for required env variables
    $envContent = file_get_contents($envPath);
    $requiredVars = ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
    
    foreach ($requiredVars as $var) {
        if (strpos($envContent, $var . '=') !== false) {
            printSuccess("Environment variable defined: $var");
        } else {
            printWarning("Missing environment variable: $var");
            $warnings++;
        }
    }
} else {
    printError(".env file missing - copy from .env.example");
    $errors++;
}

// Check 5: File permissions
printHeader("Check 5: Permissions");

$writableDirs = ['storage', 'logs'];
foreach ($writableDirs as $dir) {
    $path = $projectRoot . '/' . $dir;
    if (is_dir($path) && is_writable($path)) {
        printSuccess("Directory is writable: $dir/");
    } else {
        printWarning("Directory not writable: $dir/ - may cause issues");
        printInfo("Run: chmod -R 777 $dir");
        $warnings++;
    }
}

// Check 6: Apache/Web Server Detection
printHeader("Check 6: Web Server Environment");

if (isset($_SERVER['SERVER_SOFTWARE'])) {
    printSuccess("Running in web server: " . $_SERVER['SERVER_SOFTWARE']);
    
    // Check document root
    if (isset($_SERVER['DOCUMENT_ROOT'])) {
        $docRoot = $_SERVER['DOCUMENT_ROOT'];
        echo "Document Root: " . COLOR_BOLD . $docRoot . COLOR_RESET . "\n";
        
        // Check if document root ends with /public
        if (substr($docRoot, -7) === '/public' || substr($docRoot, -8) === '/public/') {
            printSuccess("Document Root correctly points to public/");
        } else {
            printWarning("Document Root may not point to public/ subdirectory");
            printInfo("Expected: /path/to/project/public");
            printInfo("Actual: $docRoot");
            $warnings++;
        }
    }
} else {
    printInfo("Not running in web server context (CLI mode)");
    printInfo("Cannot verify DocumentRoot - test via web browser");
}

// Summary
printHeader("Summary");

if ($errors === 0 && $warnings === 0) {
    printSuccess("All checks passed! Deployment looks good.");
    exit(0);
} elseif ($errors === 0) {
    printWarning("Checks passed with $warnings warning(s).");
    printInfo("Review warnings above - they may not affect functionality.");
    exit(0);
} else {
    printError("Found $errors error(s) and $warnings warning(s).");
    printInfo("Fix the errors above before deploying.");
    
    if ($errors > 0) {
        echo "\n" . COLOR_BOLD . "Next Steps:" . COLOR_RESET . "\n";
        echo "1. Review the errors above\n";
        echo "2. Check documentation:\n";
        echo "   - docs/DEPLOYMENT_PATH_ISSUE.md (for public/public issues)\n";
        echo "   - DEPLOYMENT_CHECKLIST.md (for deployment steps)\n";
        echo "   - README.md (for installation instructions)\n";
        echo "3. Run this script again after fixes\n";
    }
    
    exit(1);
}
