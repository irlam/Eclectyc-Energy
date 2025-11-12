#!/usr/bin/env php
<?php
/**
 * Code Quality Checker
 * 
 * Performs basic code quality checks on the codebase:
 * - Checks for SQL injection vulnerabilities
 * - Checks for XSS vulnerabilities
 * - Checks for hardcoded credentials
 * - Checks for TODO/FIXME comments
 * - Checks file permissions
 * 
 * Usage: php scripts/check_code_quality.php
 */

echo "Eclectyc Energy - Code Quality Checker\n";
echo "======================================\n\n";

$issues = [];
$warnings = [];

// Check 1: Look for potential SQL injection
echo "Checking for potential SQL injection vulnerabilities...\n";
$sqlFiles = [];
exec('grep -r "SELECT.*\$" app/ --include="*.php" -n', $sqlFiles);
foreach ($sqlFiles as $line) {
    if (!preg_match('/prepare|PDO::PARAM|bind/', $line)) {
        $warnings[] = "Potential SQL injection: " . trim($line);
    }
}
echo "  Found " . count($warnings) . " potential issues\n\n";

// Check 2: Look for hardcoded credentials
echo "Checking for hardcoded credentials...\n";
$credFiles = [];
$credCount = 0;
exec('grep -ri "password.*=.*[\'\"]\w" app/ --include="*.php" -n | grep -v "password_hash\|password_verify\|@param"', $credFiles);
foreach ($credFiles as $line) {
    if (!preg_match('/getenv|env\(|ENV\[|PASSWORD_BCRYPT/', $line)) {
        $issues[] = "Possible hardcoded credential: " . trim($line);
        $credCount++;
    }
}
echo "  Found " . count($credFiles) . " potential issues\n\n";

// Check 3: Look for echo/print of user input (XSS)
echo "Checking for potential XSS vulnerabilities...\n";
$xssFiles = [];
exec('grep -r "echo.*\$_\|print.*\$_" app/ --include="*.php" -n', $xssFiles);
foreach ($xssFiles as $line) {
    if (!preg_match('/htmlspecialchars|htmlentities|filter_var/', $line)) {
        $warnings[] = "Potential XSS: " . trim($line);
    }
}
echo "  Found " . count($xssFiles) . " potential issues\n\n";

// Check 4: Count TODO/FIXME comments
echo "Checking for TODO/FIXME comments...\n";
$todoFiles = [];
exec('grep -r "TODO\|FIXME\|XXX\|HACK" app/ --include="*.php" -n', $todoFiles);
echo "  Found " . count($todoFiles) . " TODO/FIXME comments\n";
foreach (array_slice($todoFiles, 0, 5) as $todo) {
    echo "    - " . trim($todo) . "\n";
}
if (count($todoFiles) > 5) {
    echo "    ... and " . (count($todoFiles) - 5) . " more\n";
}
echo "\n";

// Check 5: File permissions
echo "Checking file permissions...\n";
$permIssues = 0;

// Check .env file
if (file_exists('.env')) {
    $perms = fileperms('.env');
    $octal = sprintf('%o', $perms & 0777);
    if ($octal !== '644' && $octal !== '640') {
        $issues[] = ".env has insecure permissions: {$octal} (should be 644)";
        $permIssues++;
    }
}

// Check logs directory
if (is_dir('logs')) {
    $perms = fileperms('logs');
    $octal = sprintf('%o', $perms & 0777);
    if ($octal !== '755' && $octal !== '750') {
        $warnings[] = "logs/ has unusual permissions: {$octal}";
        $permIssues++;
    }
}

echo "  Found {$permIssues} permission issues\n\n";

// Check 6: Database credentials in code
echo "Checking for inline database credentials...\n";
$dbCredFiles = [];
exec('grep -ri "DB_PASSWORD\|mysql.*password" app/ --include="*.php" -n', $dbCredFiles);
$dbCredCount = 0;
foreach ($dbCredFiles as $line) {
    if (!preg_match('/getenv|env\(|ENV\[|\$_ENV/', $line)) {
        $issues[] = "Possible inline DB credential: " . trim($line);
        $dbCredCount++;
    }
}
echo "  Found {$dbCredCount} potential issues\n\n";

// Check 7: Error display settings
echo "Checking error display configuration...\n";
$errorFiles = [];
exec('grep -r "display_errors.*On\|error_reporting.*E_ALL" app/ --include="*.php" -n', $errorFiles);
if (count($errorFiles) > 0) {
    $warnings[] = "Found " . count($errorFiles) . " files with debug error display enabled";
}
echo "  Found " . count($errorFiles) . " potential issues\n\n";

// Summary
echo "======================================\n";
echo "SUMMARY\n";
echo "======================================\n\n";

if (count($issues) > 0) {
    echo "❌ CRITICAL ISSUES: " . count($issues) . "\n";
    foreach ($issues as $issue) {
        echo "  - {$issue}\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "⚠️  WARNINGS: " . count($warnings) . "\n";
    foreach (array_slice($warnings, 0, 10) as $warning) {
        echo "  - {$warning}\n";
    }
    if (count($warnings) > 10) {
        echo "  ... and " . (count($warnings) - 10) . " more\n";
    }
    echo "\n";
}

if (count($issues) === 0 && count($warnings) === 0) {
    echo "✅ No critical issues found!\n\n";
    echo "Note: This is a basic automated check. Manual code review is still recommended.\n";
} else {
    echo "Please review and address the issues found above.\n";
}

echo "\n";
exit(count($issues) > 0 ? 1 : 0);
