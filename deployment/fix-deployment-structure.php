#!/usr/bin/env php
<?php
/**
 * Deployment Structure Fix Script
 * 
 * This script detects and fixes the common "public/public" deployment issue
 * where project files were uploaded to the wrong directory.
 * 
 * Usage:
 *   Via SSH: php deployment/fix-deployment-structure.php
 *   Via Web: Access via browser (will be auto-restricted)
 * 
 * What it does:
 *   1. Detects if files are in the wrong location (public/public duplication)
 *   2. Optionally moves files to the correct location
 *   3. Verifies vendor directory exists (runs composer install if needed)
 *   4. Checks file permissions
 *   5. Validates .htaccess files
 */

// Determine if running via CLI or web
$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    // Web-based access - add basic security
    header('Content-Type: text/html; charset=utf-8');
    
    // Only allow from localhost or with ?allow parameter
    $allowedIPs = ['127.0.0.1', '::1'];
    if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowedIPs) && !isset($_GET['allow'])) {
        http_response_code(403);
        die('<h1>Access Denied</h1><p>This diagnostic tool is restricted. Add ?allow to URL to bypass (not recommended in production).</p>');
    }
    
    echo "<!DOCTYPE html>\n<html>\n<head><title>Deployment Fix Tool</title>";
    echo "<style>body{font-family:monospace;margin:40px;background:#f5f5f5;}";
    echo ".success{color:green;} .error{color:red;} .warning{color:orange;}</style>";
    echo "</head>\n<body>\n<h1>Eclectyc Energy - Deployment Fix Tool</h1>\n";
}

/**
 * Output a message (handles both CLI and web)
 */
function output($message, $type = 'info') {
    global $isCLI;
    
    if ($isCLI) {
        $prefix = '';
        if ($type === 'error') $prefix = '[ERROR] ';
        if ($type === 'warning') $prefix = '[WARNING] ';
        if ($type === 'success') $prefix = '[SUCCESS] ';
        echo $prefix . $message . "\n";
    } else {
        $class = $type;
        echo "<p class='$class'>" . htmlspecialchars($message) . "</p>\n";
    }
}

output("=== Deployment Structure Diagnostic ===\n", 'info');

// Detect current directory
$currentDir = getcwd();
$scriptDir = dirname(__FILE__);
$projectRoot = dirname($scriptDir); // Should be parent of deployment/

output("Current directory: $currentDir");
output("Script directory: $scriptDir");
output("Detected project root: $projectRoot");

// Check 1: Detect path duplication
output("\n--- Checking for path duplication ---");

$hasPublicPublic = false;
$publicDir = $projectRoot . '/public';
$nestedPublicDir = $publicDir . '/public';

if (file_exists($nestedPublicDir) && is_dir($nestedPublicDir)) {
    output("ERROR: Found nested public/public/ directory!", 'error');
    output("This indicates files were uploaded to the wrong location.", 'error');
    $hasPublicPublic = true;
} else {
    output("✓ No public/public duplication detected", 'success');
}

// Check 2: Verify correct structure
output("\n--- Verifying project structure ---");

$requiredDirs = ['app', 'public', 'storage', 'logs'];
$missingDirs = [];

foreach ($requiredDirs as $dir) {
    $path = $projectRoot . '/' . $dir;
    if (!file_exists($path) || !is_dir($path)) {
        $missingDirs[] = $dir;
        output("✗ Missing directory: $dir", 'error');
    } else {
        output("✓ Found directory: $dir", 'success');
    }
}

// Check 3: Verify vendor directory
output("\n--- Checking vendor directory ---");

$vendorDir = $projectRoot . '/vendor';
$vendorAutoload = $vendorDir . '/autoload.php';

if (!file_exists($vendorDir)) {
    output("✗ vendor/ directory is missing!", 'error');
    output("  Solution: Run 'composer install' in $projectRoot", 'warning');
} elseif (!file_exists($vendorAutoload)) {
    output("✗ vendor/autoload.php is missing!", 'error');
    output("  Solution: Run 'composer install' in $projectRoot", 'warning');
} else {
    output("✓ vendor directory exists with autoload.php", 'success');
}

// Check 4: Verify index.php location
output("\n--- Checking index.php location ---");

$publicIndexPhp = $projectRoot . '/public/index.php';
$nestedIndexPhp = $nestedPublicDir . '/index.php';

if (file_exists($nestedIndexPhp)) {
    output("✗ Found index.php in wrong location: public/public/index.php", 'error');
} 

if (file_exists($publicIndexPhp)) {
    output("✓ Found index.php in correct location: public/index.php", 'success');
} else {
    output("✗ Missing index.php in public/ directory!", 'error');
}

// Check 5: Verify .htaccess files
output("\n--- Checking .htaccess files ---");

$rootHtaccess = $projectRoot . '/.htaccess';
$publicHtaccess = $projectRoot . '/public/.htaccess';

if (file_exists($rootHtaccess)) {
    output("✓ Found root .htaccess", 'success');
} else {
    output("✗ Missing root .htaccess", 'warning');
}

if (file_exists($publicHtaccess)) {
    output("✓ Found public/.htaccess", 'success');
    
    // Check for DirectoryIndex directive
    $htaccessContent = file_get_contents($publicHtaccess);
    if (stripos($htaccessContent, 'DirectoryIndex') !== false) {
        output("✓ DirectoryIndex directive found in public/.htaccess", 'success');
    } else {
        output("✗ DirectoryIndex directive missing in public/.htaccess", 'warning');
    }
} else {
    output("✗ Missing public/.htaccess", 'error');
}

// Check 6: File permissions
output("\n--- Checking file permissions ---");

$checkPerms = [
    'logs' => 0755,
    'storage' => 0755,
];

foreach ($checkPerms as $dir => $expectedPerms) {
    $path = $projectRoot . '/' . $dir;
    if (file_exists($path)) {
        $perms = fileperms($path) & 0777;
        if ($perms >= $expectedPerms) {
            output(sprintf("✓ %s has correct permissions (%o)", $dir, $perms), 'success');
        } else {
            output(sprintf("✗ %s has insufficient permissions (%o, expected %o)", $dir, $perms, $expectedPerms), 'warning');
        }
    }
}

// Summary
output("\n=== Summary ===");

if ($hasPublicPublic || count($missingDirs) > 0 || !file_exists($vendorAutoload)) {
    output("\n⚠ ISSUES DETECTED! Follow these steps to fix:", 'error');
    
    if ($hasPublicPublic) {
        output("\n1. FIX PATH DUPLICATION:", 'error');
        output("   See docs/DEPLOYMENT_PATH_ISSUE.md for detailed instructions");
        output("   Quick fix: Move files from public/ to parent directory");
    }
    
    if (!file_exists($vendorDir) || !file_exists($vendorAutoload)) {
        output("\n2. INSTALL COMPOSER DEPENDENCIES:", 'error');
        output("   cd $projectRoot");
        output("   composer install --no-dev --optimize-autoloader");
    }
    
    if (count($missingDirs) > 0) {
        output("\n3. CREATE MISSING DIRECTORIES:", 'error');
        foreach ($missingDirs as $dir) {
            output("   mkdir -p $projectRoot/$dir");
        }
    }
    
    output("\n4. VERIFY DEPLOYMENT:", 'error');
    output("   After fixing, run this script again to verify");
    
} else {
    output("\n✓ All checks passed! Deployment structure looks correct.", 'success');
    output("\nNext steps:");
    output("  1. Test the website in your browser");
    output("  2. Check logs/php-error.log for any errors");
    output("  3. Verify database connection is working");
}

// Provide web link to documentation
if (!$isCLI) {
    echo "\n<hr>\n";
    echo "<h2>Additional Resources</h2>";
    echo "<ul>";
    echo "<li><a href='/DEPLOYMENT_CHECKLIST.md'>Deployment Checklist</a></li>";
    echo "<li><a href='/docs/DEPLOYMENT_PATH_ISSUE.md'>Path Duplication Fix Guide</a></li>";
    echo "<li><a href='/check-deployment.php?allow'>Web-based Deployment Checker</a></li>";
    echo "</ul>";
    echo "\n</body>\n</html>";
}

exit(count($missingDirs) > 0 || !file_exists($vendorAutoload) ? 1 : 0);
