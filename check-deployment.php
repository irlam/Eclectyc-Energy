<?php
/**
 * Web-based Deployment Structure Checker
 * 
 * Access this file via browser to check deployment status
 * URL: https://yourdomain.com/check-deployment.php
 * 
 * For security, this file should be deleted after deployment verification
 */

// Security: Only allow access from localhost or specific IPs
$allowedIPs = ['127.0.0.1', '::1']; // Add your IP if needed

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowedIPs) && !isset($_GET['allow'])) {
    // Comment out the next 3 lines to allow access from any IP (NOT RECOMMENDED for production)
    header('HTTP/1.1 403 Forbidden');
    die('Access denied. Add ?allow to URL to bypass (not recommended in production)');
}

// Set content type
header('Content-Type: text/html; charset=utf-8');

$projectRoot = dirname(__FILE__);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Eclectyc Energy - Deployment Checker</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #0066cc;
            padding-bottom: 10px;
        }
        h2 {
            color: #0066cc;
            margin-top: 30px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 8px;
        }
        .success {
            color: #28a745;
            padding: 8px 12px;
            margin: 5px 0;
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        .error {
            color: #dc3545;
            padding: 8px 12px;
            margin: 5px 0;
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .warning {
            color: #856404;
            padding: 8px 12px;
            margin: 5px 0;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        .info {
            color: #004085;
            padding: 8px 12px;
            margin: 5px 0;
            background: #d1ecf1;
            border-left: 4px solid #17a2b8;
        }
        .summary {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
        }
        .code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 14px;
        }
        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Deployment Structure Checker</h1>
        <p><strong>Project Root:</strong> <span class="code"><?= htmlspecialchars($projectRoot) ?></span></p>
        <p><strong>Check Time:</strong> <?= date('Y-m-d H:i:s') ?></p>

<?php

$errors = 0;
$warnings = 0;

function printSuccess($text) {
    echo '<div class="success">✓ ' . htmlspecialchars($text) . '</div>';
}

function printError($text) {
    global $errors;
    $errors++;
    echo '<div class="error">✗ ' . htmlspecialchars($text) . '</div>';
}

function printWarning($text) {
    global $warnings;
    $warnings++;
    echo '<div class="warning">⚠ ' . htmlspecialchars($text) . '</div>';
}

function printInfo($text) {
    echo '<div class="info">ℹ ' . htmlspecialchars($text) . '</div>';
}

// Check 1: Directory Structure
echo '<h2>Check 1: Directory Structure</h2>';

$requiredDirs = ['app', 'public', 'database', 'storage'];
$requiredFiles = ['composer.json', 'public/index.php'];

foreach ($requiredDirs as $dir) {
    $path = $projectRoot . '/' . $dir;
    if (is_dir($path)) {
        printSuccess("Directory exists: $dir/");
    } else {
        printError("Missing directory: $dir/");
    }
}

foreach ($requiredFiles as $file) {
    $path = $projectRoot . '/' . $file;
    if (file_exists($path)) {
        printSuccess("File exists: $file");
    } else {
        printError("Missing file: $file");
    }
}

// Check 2: Path Duplication
echo '<h2>Check 2: Path Duplication Detection</h2>';

$publicPublic = $projectRoot . '/public/public';
if (is_dir($publicPublic)) {
    printError("CRITICAL: Detected public/public/ directory!");
    printInfo("Files were uploaded to wrong location. See docs/DEPLOYMENT_PATH_ISSUE.md");
} else {
    printSuccess("No public/public/ duplication detected");
}

// Check for misplaced files
$misplacedFiles = [
    'public/app' => 'app/ directory inside public/',
    'public/vendor' => 'vendor/ directory inside public/',
    'public/composer.json' => 'composer.json inside public/'
];

foreach ($misplacedFiles as $path => $description) {
    $fullPath = $projectRoot . '/' . $path;
    if (file_exists($fullPath)) {
        printWarning("Found misplaced: $description");
    }
}

// Check 3: Dependencies
echo '<h2>Check 3: Dependencies</h2>';

$vendorPath = $projectRoot . '/vendor';
if (is_dir($vendorPath)) {
    printSuccess("Vendor directory exists");
    
    $autoloadPath = $vendorPath . '/autoload.php';
    if (file_exists($autoloadPath)) {
        printSuccess("Composer autoload.php exists");
    } else {
        printError("Missing vendor/autoload.php - run 'composer install'");
    }
} else {
    printError("Vendor directory missing - run 'composer install'");
    printInfo("Run: composer install --no-dev --optimize-autoloader");
}

// Check 4: Configuration
echo '<h2>Check 4: Configuration</h2>';

$envPath = $projectRoot . '/.env';
if (file_exists($envPath)) {
    printSuccess(".env file exists");
    
    $envContent = file_get_contents($envPath);
    $requiredVars = ['DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
    
    foreach ($requiredVars as $var) {
        if (strpos($envContent, $var . '=') !== false) {
            printSuccess("Environment variable defined: $var");
        } else {
            printWarning("Missing environment variable: $var");
        }
    }
} else {
    printError(".env file missing - copy from .env.example");
}

// Check 5: Permissions
echo '<h2>Check 5: Permissions</h2>';

$writableDirs = ['storage', 'logs'];
foreach ($writableDirs as $dir) {
    $path = $projectRoot . '/' . $dir;
    if (is_dir($path)) {
        if (is_writable($path)) {
            printSuccess("Directory is writable: $dir/");
        } else {
            printWarning("Directory not writable: $dir/ - may cause issues");
            printInfo("Run: chmod -R 777 $dir");
        }
    }
}

// Check 6: Web Server Environment
echo '<h2>Check 6: Web Server Environment</h2>';

if (isset($_SERVER['SERVER_SOFTWARE'])) {
    printSuccess("Server: " . $_SERVER['SERVER_SOFTWARE']);
    
    if (isset($_SERVER['DOCUMENT_ROOT'])) {
        $docRoot = $_SERVER['DOCUMENT_ROOT'];
        echo '<p><strong>Document Root:</strong> <span class="code">' . htmlspecialchars($docRoot) . '</span></p>';
        
        // Check if document root ends with /public
        if (substr($docRoot, -7) === '/public' || substr($docRoot, -8) === '/public/') {
            printSuccess("Document Root correctly points to public/");
        } else {
            printWarning("Document Root may not point to public/ subdirectory");
            printInfo("Expected: /path/to/project/public");
            printInfo("Actual: $docRoot");
        }
        
        // Check if current file is in DocumentRoot
        $currentFile = $_SERVER['SCRIPT_FILENAME'];
        echo '<p><strong>This File:</strong> <span class="code">' . htmlspecialchars($currentFile) . '</span></p>';
        
        if (strpos($currentFile, '/public/public/') !== false) {
            printError("CRITICAL: This file path contains 'public/public/' - deployment is incorrect!");
            printInfo("See docs/DEPLOYMENT_PATH_ISSUE.md for fix instructions");
        }
    }
} else {
    printInfo("Could not detect web server information");
}

// Check 7: PHP Version
echo '<h2>Check 7: PHP Environment</h2>';

$phpVersion = PHP_VERSION;
echo '<p><strong>PHP Version:</strong> <span class="code">' . htmlspecialchars($phpVersion) . '</span></p>';

if (version_compare($phpVersion, '8.2.0', '>=')) {
    printSuccess("PHP version is 8.2+ (required)");
} else {
    printError("PHP version is below 8.2 - upgrade required");
}

// Check required extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'curl'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        printSuccess("PHP extension loaded: $ext");
    } else {
        printError("Missing PHP extension: $ext");
    }
}

// Summary
echo '<div class="summary">';
echo '<h2>Summary</h2>';

if ($errors === 0 && $warnings === 0) {
    echo '<div class="success">✓ All checks passed! Deployment looks good.</div>';
    echo '<p>Your application should be working correctly.</p>';
} elseif ($errors === 0) {
    echo '<div class="warning">⚠ Checks passed with ' . $warnings . ' warning(s).</div>';
    echo '<p>Review warnings above - they may not affect functionality.</p>';
} else {
    echo '<div class="error">✗ Found ' . $errors . ' error(s) and ' . $warnings . ' warning(s).</div>';
    echo '<p>Fix the errors above before the application will work properly.</p>';
    
    echo '<h3>Next Steps:</h3>';
    echo '<ol>';
    echo '<li>Review the errors above</li>';
    echo '<li>Check documentation:';
    echo '<ul>';
    echo '<li><a href="docs/DEPLOYMENT_PATH_ISSUE.md">docs/DEPLOYMENT_PATH_ISSUE.md</a> (for public/public issues)</li>';
    echo '<li><a href="DEPLOYMENT_CHECKLIST.md">DEPLOYMENT_CHECKLIST.md</a> (for deployment steps)</li>';
    echo '<li><a href="README.md">README.md</a> (for installation instructions)</li>';
    echo '</ul></li>';
    echo '<li>Run composer install if vendor is missing</li>';
    echo '<li>Refresh this page after fixes</li>';
    echo '</ol>';
}

echo '</div>';

?>

        <hr style="margin: 40px 0;">
        <p style="color: #666; font-size: 14px;">
            <strong>⚠️ Security Note:</strong> Delete this file after verifying deployment!<br>
            This file is for diagnostic purposes only and should not remain on production servers.
        </p>
    </div>
</body>
</html>
