#!/usr/bin/env php
<?php
/**
 * eclectyc-energy/scripts/check_system_health.php
 * Diagnostic script to show system health status and degraded faults
 * Usage: php scripts/check_system_health.php [--verbose]
 */

require __DIR__ . '/../vendor/autoload.php';

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$verbose = in_array('--verbose', $argv) || in_array('-v', $argv);

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║         Eclectyc Energy - System Health Diagnostic          ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Fetch health data from API or run checks directly
$healthUrl = ($_ENV['APP_URL'] ?? 'http://localhost') . '/api/health';

try {
    // Try to fetch from API endpoint
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);
    
    $response = @file_get_contents($healthUrl, false, $context);
    
    if ($response === false) {
        echo "⚠️  Warning: Could not reach health API at $healthUrl\n";
        echo "   Running local checks instead...\n\n";
        $health = runLocalHealthChecks();
    } else {
        $health = json_decode($response, true);
    }
} catch (Exception $e) {
    echo "⚠️  Warning: " . $e->getMessage() . "\n\n";
    $health = runLocalHealthChecks();
}

// Display overall status
$statusEmoji = match($health['status'] ?? 'unknown') {
    'healthy' => '✅',
    'degraded' => '⚠️ ',
    'critical' => '❌',
    default => '❓',
};

echo "Overall Status: $statusEmoji " . strtoupper($health['status'] ?? 'UNKNOWN') . "\n";
echo "Timestamp: " . ($health['timestamp'] ?? date(DATE_ATOM)) . "\n";
echo "\n";

// Check for degraded services
$degradedChecks = [];
$criticalChecks = [];
$healthyChecks = [];

if (isset($health['checks']) && is_array($health['checks'])) {
    foreach ($health['checks'] as $checkName => $checkData) {
        $checkStatus = $checkData['status'] ?? 'unknown';
        
        if ($checkStatus === 'degraded') {
            $degradedChecks[$checkName] = $checkData;
        } elseif ($checkStatus === 'critical') {
            $criticalChecks[$checkName] = $checkData;
        } elseif ($checkStatus === 'healthy') {
            $healthyChecks[$checkName] = $checkData;
        }
    }
}

// Display critical issues first
if (!empty($criticalChecks)) {
    echo "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║  ❌ CRITICAL ISSUES                                       ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n";
    echo "\n";
    
    foreach ($criticalChecks as $name => $check) {
        displayCheckDetails($name, $check, $verbose);
    }
}

// Display degraded services
if (!empty($degradedChecks)) {
    echo "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║  ⚠️  DEGRADED SERVICES (Non-Critical)                     ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n";
    echo "\n";
    
    foreach ($degradedChecks as $name => $check) {
        displayCheckDetails($name, $check, $verbose);
    }
}

// Display healthy services if verbose
if ($verbose && !empty($healthyChecks)) {
    echo "╔═══════════════════════════════════════════════════════════╗\n";
    echo "║  ✅ HEALTHY SERVICES                                      ║\n";
    echo "╚═══════════════════════════════════════════════════════════╝\n";
    echo "\n";
    
    foreach ($healthyChecks as $name => $check) {
        displayCheckDetails($name, $check, $verbose);
    }
}

// Summary
echo "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo " Summary\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo " ✅ Healthy:  " . count($healthyChecks) . "\n";
echo " ⚠️  Degraded: " . count($degradedChecks) . "\n";
echo " ❌ Critical: " . count($criticalChecks) . "\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "\n";

// Provide actionable recommendations
if (!empty($degradedChecks) || !empty($criticalChecks)) {
    echo "📋 Recommendations:\n";
    echo "\n";
    
    if (isset($degradedChecks['sftp'])) {
        echo "  • SFTP: This is optional. If you're not using exports, you can ignore this.\n";
        echo "    To fix: Configure SFTP_HOST, SFTP_USERNAME, and SFTP_PASSWORD in .env\n\n";
    }
    
    if (isset($degradedChecks['recent_activity'])) {
        echo "  • Recent Activity: No recent imports/exports detected.\n";
        echo "    To fix: Import data via /admin/imports or adjust thresholds in .env\n";
        echo "    (HEALTH_MAX_IMPORT_HOURS, HEALTH_MAX_EXPORT_HOURS)\n\n";
    }
    
    if (isset($degradedChecks['environment']) || isset($criticalChecks['environment'])) {
        echo "  • Environment: Missing required environment variables.\n";
        echo "    To fix: Check .env file and ensure all required variables are set.\n\n";
    }
    
    if (isset($degradedChecks['database']) || isset($criticalChecks['database'])) {
        echo "  • Database: Connection or table issues detected.\n";
        echo "    To fix: Run migrations with 'php scripts/migrate.php'\n\n";
    }
    
    if (isset($degradedChecks['filesystem']) || isset($criticalChecks['filesystem'])) {
        echo "  • Filesystem: Directory permission issues.\n";
        echo "    To fix: chmod 755 logs exports storage\n\n";
    }
    
    echo "  📖 For detailed troubleshooting: docs/troubleshooting_system_degraded.md\n";
}

echo "\n";

function displayCheckDetails(string $name, array $check, bool $verbose): void
{
    $statusIcon = match($check['status'] ?? 'unknown') {
        'healthy' => '✅',
        'degraded' => '⚠️ ',
        'critical' => '❌',
        default => '❓',
    };
    
    echo "  $statusIcon " . strtoupper(str_replace('_', ' ', $name)) . "\n";
    echo "     Status: " . ($check['status'] ?? 'unknown') . "\n";
    
    if (!empty($check['message'])) {
        echo "     Message: " . $check['message'] . "\n";
    }
    
    if ($verbose) {
        // Show additional details
        if (isset($check['missing_keys']) && is_array($check['missing_keys']) && !empty($check['missing_keys'])) {
            echo "     Missing keys: " . implode(', ', $check['missing_keys']) . "\n";
        }
        
        if (isset($check['checks']) && is_array($check['checks'])) {
            foreach ($check['checks'] as $subCheck => $value) {
                $icon = $value ? '✓' : '✗';
                echo "       $icon " . str_replace('_', ' ', $subCheck) . "\n";
            }
        }
        
        if (isset($check['tables']) && is_array($check['tables'])) {
            foreach ($check['tables'] as $table => $exists) {
                $icon = $exists ? '✓' : '✗';
                echo "       $icon Table: $table\n";
            }
        }
        
        if (isset($check['details']) && is_array($check['details'])) {
            foreach ($check['details'] as $key => $value) {
                if ($value !== null) {
                    echo "       • " . str_replace('_', ' ', $key) . ": $value\n";
                }
            }
        }
    }
    
    echo "\n";
}

function runLocalHealthChecks(): array
{
    $health = [
        'status' => 'healthy',
        'timestamp' => date(DATE_ATOM),
        'checks' => [],
    ];
    
    // Check environment
    $envCheck = checkEnvironment();
    $health['checks']['environment'] = $envCheck;
    $health['status'] = adjustStatus($health['status'], $envCheck);
    
    // Check database
    $dbCheck = checkDatabase();
    $health['checks']['database'] = $dbCheck;
    $health['status'] = adjustStatus($health['status'], $dbCheck);
    
    // Check filesystem
    $fsCheck = checkFileSystem();
    $health['checks']['filesystem'] = $fsCheck;
    $health['status'] = adjustStatus($health['status'], $fsCheck);
    
    return $health;
}

function checkEnvironment(): array
{
    $required = ['APP_KEY', 'APP_URL', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];
    $missing = [];
    
    foreach ($required as $key) {
        if (!isset($_ENV[$key]) || $_ENV[$key] === '') {
            $missing[] = $key;
        }
    }
    
    $healthy = empty($missing);
    
    return [
        'healthy' => $healthy,
        'status' => $healthy ? 'healthy' : 'degraded',
        'missing_keys' => $missing,
        'message' => $healthy ? 'Environment OK' : 'Missing environment variables',
    ];
}

function checkDatabase(): array
{
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $_ENV['DB_HOST'] ?? 'localhost',
            $_ENV['DB_PORT'] ?? 3306,
            $_ENV['DB_DATABASE'] ?? ''
        );
        
        $pdo = new PDO(
            $dsn,
            $_ENV['DB_USERNAME'] ?? '',
            $_ENV['DB_PASSWORD'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $pdo->query('SELECT 1');
        
        return [
            'healthy' => true,
            'status' => 'healthy',
            'message' => 'Database connected',
        ];
    } catch (Exception $e) {
        return [
            'healthy' => false,
            'status' => 'critical',
            'message' => 'Database connection failed: ' . $e->getMessage(),
        ];
    }
}

function checkFileSystem(): array
{
    $basePath = dirname(__DIR__);
    $checks = [
        'logs_writable' => is_writable($basePath . '/logs'),
        'storage_writable' => is_writable($basePath . '/storage'),
        'env_exists' => file_exists($basePath . '/.env'),
    ];
    
    $healthy = !in_array(false, $checks, true);
    
    return [
        'healthy' => $healthy,
        'status' => $healthy ? 'healthy' : 'degraded',
        'checks' => $checks,
        'message' => $healthy ? 'Filesystem OK' : 'Filesystem issues detected',
    ];
}

function adjustStatus(string $current, array $check): string
{
    $checkStatus = $check['status'] ?? 'healthy';
    
    if ($checkStatus === 'critical') {
        return 'critical';
    }
    
    if ($checkStatus === 'degraded' && $current !== 'critical') {
        return 'degraded';
    }
    
    return $current;
}
