<?php
/**
 * eclectyc-energy/app/http/Controllers/Api/HealthController.php
 * Health check controller for system status monitoring
 * Last updated: 06/11/2024 14:45:00
 */

namespace App\Http\Controllers\Api;

use DateTimeImmutable;
use Exception;
use PDO;
use PDOException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HealthController
{
    protected ContainerInterface $container;
    
    /**
     * Constructor
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }
    
    /**
     * Health check endpoint
     */
    public function check(Request $request, Response $response): Response
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => date(DATE_ATOM),
            'timezone' => date_default_timezone_get(),
            'environment' => $_ENV['APP_ENV'] ?? 'unknown',
            'checks' => []
        ];

        $health['checks']['environment'] = $this->checkEnvironment();
        $health['status'] = $this->adjustStatus($health['status'], $health['checks']['environment']);

        // Check database connectivity
        $dbCheck = $this->checkDatabase();
        $health['checks']['database'] = $dbCheck;
        $health['status'] = $this->adjustStatus($health['status'], $dbCheck);

        // Check file system
        $fsCheck = $this->checkFileSystem();
        $health['checks']['filesystem'] = $fsCheck;
        $health['status'] = $this->adjustStatus($health['status'], $fsCheck);

        // Check PHP version
        $phpCheck = $this->checkPhpVersion();
        $health['checks']['php'] = $phpCheck;
        $health['status'] = $this->adjustStatus($health['status'], $phpCheck);

        // Check SFTP configuration
        $sftpCheck = $this->checkSftpConfiguration();
        $health['checks']['sftp'] = $sftpCheck;
        $health['status'] = $this->adjustStatus($health['status'], $sftpCheck);

        // Check recent ingest/export activity
        $activityCheck = $this->checkRecentActivity();
        $health['checks']['recent_activity'] = $activityCheck;
        $health['status'] = $this->adjustStatus($health['status'], $activityCheck);

        // Memory usage
        $health['checks']['memory'] = [
            'healthy' => true,
            'status' => 'healthy',
            'current' => $this->formatBytes(memory_get_usage()),
            'peak' => $this->formatBytes(memory_get_peak_usage()),
            'limit' => ini_get('memory_limit')
        ];

        // Disk space
        $diskCheck = $this->checkDiskSpace();
        $health['checks']['disk'] = $diskCheck;
        $health['status'] = $this->adjustStatus($health['status'], $diskCheck);

        // Application version
        $health['version'] = '1.0.0';
        $health['api_version'] = 'v1';
        $health['host'] = gethostname();

        // Response
        $response->getBody()->write(json_encode($health, JSON_PRETTY_PRINT));

        $statusCode = $health['status'] === 'healthy' ? 200 : ($health['status'] === 'critical' ? 503 : 207);

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }
    
    /**
     * Check database connectivity
     */
    protected function checkDatabase(): array
    {
        try {
            /** @var PDO|null $db */
            $db = $this->container->get('db');

            if (!$db) {
                // Provide more detailed error message
                $dbConfig = [
                    'host' => $_ENV['DB_HOST'] ?? 'not set',
                    'database' => $_ENV['DB_DATABASE'] ?? $_ENV['DB_NAME'] ?? 'not set',
                    'username' => $_ENV['DB_USERNAME'] ?? $_ENV['DB_USER'] ?? 'not set',
                ];
                
                return [
                    'healthy' => false,
                    'status' => 'critical',
                    'message' => 'Database connection failed - check credentials and server availability',
                    'config' => $dbConfig,
                    'hint' => 'Verify .env file exists and contains correct DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD'
                ];
            }

            $db->query('SELECT 1');

            $tables = [];
            $requiredTables = ['users', 'sites', 'meters', 'meter_readings'];

            $stmt = $db->query('SHOW TABLES');
            $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($requiredTables as $table) {
                $tables[$table] = in_array($table, $existingTables, true);
            }

            $allTablesExist = !in_array(false, $tables, true);

            $migrations = null;
            try {
                $migrationStmt = $db->query('SELECT COUNT(*) FROM migrations');
                $migrations = (int) $migrationStmt->fetchColumn();
            } catch (PDOException $exception) {
                $migrations = null;
            }

            return [
                'healthy' => true,
                'status' => $allTablesExist ? 'healthy' : 'degraded',
                'connected' => true,
                'tables' => $tables,
                'all_tables_exist' => $allTablesExist,
                'migrations_count' => $migrations,
                'message' => $allTablesExist ? 'Database operational' : 'Some tables missing - run migrations'
            ];
        } catch (Exception $e) {
            return [
                'healthy' => false,
                'status' => 'critical',
                'connected' => false,
                'message' => 'Database connection failed',
                'error' => $_ENV['APP_DEBUG'] ? $e->getMessage() : 'Check configuration'
            ];
        }
    }
    
    /**
     * Check file system permissions
     */
    protected function checkFileSystem(): array
    {
        $checks = [];
        $healthy = true;
        
        // Check logs directory
        $logsDir = BASE_PATH . '/logs';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0777, true);
        }
        
        $checks['logs_writable'] = is_writable($logsDir);
        if (!$checks['logs_writable']) {
            $healthy = false;
        }
        
        $exportsDir = BASE_PATH . '/exports';
        if (!is_dir($exportsDir)) {
            mkdir($exportsDir, 0777, true);
        }

        $checks['exports_writable'] = is_writable($exportsDir);
        if (!$checks['exports_writable']) {
            $healthy = false;
        }

        // Check .env file
        $envFile = BASE_PATH . '/.env';
        $checks['env_exists'] = file_exists($envFile);
        $checks['env_readable'] = is_readable($envFile);
        
        if (!$checks['env_exists'] || !$checks['env_readable']) {
            $healthy = false;
        }
        
        return [
            'healthy' => $healthy,
            'status' => $healthy ? 'healthy' : 'degraded',
            'checks' => $checks,
            'message' => $healthy ? 'File system operational' : 'File system issues detected'
        ];
    }
    
    /**
     * Check PHP version
     */
    protected function checkPhpVersion(): array
    {
        $currentVersion = PHP_VERSION;
        $requiredVersion = '8.2.0';
        $isValid = version_compare($currentVersion, $requiredVersion, '>=');
        
        return [
            'healthy' => $isValid,
            'status' => $isValid ? 'healthy' : 'degraded',
            'current' => $currentVersion,
            'required' => $requiredVersion,
            'message' => $isValid ? 'PHP version OK' : 'PHP version too old'
        ];
    }
    
    /**
     * Check disk space
     */
    protected function checkDiskSpace(): array
    {
        $path = BASE_PATH;
        $free = disk_free_space($path);
        $total = disk_total_space($path);
        $used = $total - $free;
        $percentUsed = round(($used / $total) * 100, 2);
        
        $healthy = $percentUsed < 90;

        return [
            'healthy' => $healthy,
            'status' => $healthy ? 'healthy' : 'degraded',
            'free' => $this->formatBytes($free),
            'total' => $this->formatBytes($total),
            'used' => $this->formatBytes($used),
            'percent_used' => $percentUsed . '%',
            'message' => $healthy ? 'Disk space OK' : 'Low disk space warning'
        ];
    }
    
    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    protected function checkEnvironment(): array
    {
        $required = [
            'APP_KEY',
            'APP_URL',
            'DB_HOST',
            'DB_DATABASE',
            'DB_USERNAME',
            'DB_PASSWORD',
        ];

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
            'required_keys' => $required,
            'missing_keys' => $missing,
            'message' => $healthy ? 'Environment configuration complete' : 'Missing environment variables'
        ];
    }

    protected function checkSftpConfiguration(): array
    {
        $host = $_ENV['SFTP_HOST'] ?? null;
        $username = $_ENV['SFTP_USERNAME'] ?? null;
        $password = $_ENV['SFTP_PASSWORD'] ?? null;
        $privateKey = $_ENV['SFTP_PRIVATE_KEY'] ?? null;
        $port = (int) ($_ENV['SFTP_PORT'] ?? 22);

        $configured = $host && $username && ($password || $privateKey);
        $status = $configured ? 'healthy' : 'degraded';

        $details = [
            'host' => $host,
            'port' => $port,
            'username' => $username ? 'configured' : null,
            'auth_mode' => $privateKey ? 'key' : ($password ? 'password' : null),
        ];

        $message = $configured
            ? 'SFTP credentials configured'
            : 'Incomplete SFTP configuration';

        return [
            'healthy' => $configured,
            'status' => $status,
            'details' => $details,
            'message' => $message
        ];
    }

    protected function checkRecentActivity(): array
    {
        try {
            /** @var PDO|null $db */
            $db = $this->container->get('db');
            if (!$db) {
                return [
                    'healthy' => false,
                    'status' => 'degraded',
                    'message' => 'Skipped recent activity check (database unavailable)'
                ];
            }

            $now = new DateTimeImmutable('now');
            $exportThresholdHours = (int) ($_ENV['HEALTH_MAX_EXPORT_HOURS'] ?? 48);
            $importThresholdHours = (int) ($_ENV['HEALTH_MAX_IMPORT_HOURS'] ?? 24);

            $exports = $this->fetchLatestTimestamp($db, 'exports', 'completed_at');
            $imports = $this->fetchLatestImport($db);

            $status = 'healthy';
            $healthy = true;
            $warnings = [];

            if ($exports['timestamp'] instanceof DateTimeImmutable) {
                $exportAge = $now->getTimestamp() - $exports['timestamp']->getTimestamp();
                $exports['age_hours'] = round($exportAge / 3600, 2);
                if ($exportAge > $exportThresholdHours * 3600) {
                    $healthy = false;
                    $status = 'degraded';
                    $warnings[] = 'No exports within threshold';
                }
            } elseif (($exports['exists'] ?? false) === false) {
                $healthy = false;
                $status = 'degraded';
                $warnings[] = 'Exports table not found';
            } else {
                $warnings[] = 'No exports recorded yet';
            }

            if ($imports['timestamp'] instanceof DateTimeImmutable) {
                $importAge = $now->getTimestamp() - $imports['timestamp']->getTimestamp();
                $imports['age_hours'] = round($importAge / 3600, 2);
                if ($importAge > $importThresholdHours * 3600) {
                    $healthy = false;
                    $status = 'degraded';
                    $warnings[] = 'No imports within threshold';
                }
            } elseif (($imports['exists'] ?? false) === false) {
                $healthy = false;
                $status = 'degraded';
                $warnings[] = 'Audit logs table not found';
            } else {
                $warnings[] = 'No imports recorded yet';
            }

            if (!empty($exports['error']) || !empty($imports['error'])) {
                $healthy = false;
                $status = 'degraded';
                $warnings[] = 'Errors encountered while reading activity tables';
            }

            // Build a more descriptive message
            $message = $healthy 
                ? 'Recent activity within thresholds' 
                : 'Activity warnings: ' . implode(', ', $warnings);
            
            return [
                'healthy' => $healthy,
                'status' => $status,
                'exports' => $this->serialiseActivity($exports),
                'imports' => $this->serialiseActivity($imports),
                'message' => $message,
                'warnings' => $warnings
            ];
        } catch (Exception $exception) {
            return [
                'healthy' => false,
                'status' => 'degraded',
                'message' => 'Failed to evaluate recent activity',
                'error' => $_ENV['APP_DEBUG'] ? $exception->getMessage() : 'Enable debug for more detail'
            ];
        }
    }

    private function fetchLatestTimestamp(PDO $db, string $table, string $column): array
    {
        try {
            $exists = $this->tableExists($db, $table);
            if (!$exists) {
                return [
                    'exists' => false,
                    'timestamp' => null,
                ];
            }

            $stmt = $db->query(sprintf('SELECT MAX(%s) FROM %s', $column, $table));
            $value = $stmt->fetchColumn();

            if (!$value) {
                return [
                    'exists' => true,
                    'timestamp' => null,
                ];
            }

            return [
                'exists' => true,
                'timestamp' => new DateTimeImmutable((string) $value),
            ];
        } catch (Exception $exception) {
            return [
                'exists' => false,
                'timestamp' => null,
                'error' => $_ENV['APP_DEBUG'] ? $exception->getMessage() : null,
            ];
        }
    }

    private function fetchLatestImport(PDO $db): array
    {
        try {
            $exists = $this->tableExists($db, 'audit_logs');
            if (!$exists) {
                return [
                    'exists' => false,
                    'timestamp' => null
                ];
            }

            $stmt = $db->prepare('SELECT MAX(created_at) FROM audit_logs WHERE action = :action');
            $stmt->execute(['action' => 'import_csv']);
            $value = $stmt->fetchColumn();

            if (!$value) {
                return [
                    'exists' => true,
                    'timestamp' => null
                ];
            }

            return [
                'exists' => true,
                'timestamp' => new DateTimeImmutable((string) $value)
            ];
        } catch (Exception $exception) {
            return [
                'exists' => false,
                'timestamp' => null,
                'error' => $_ENV['APP_DEBUG'] ? $exception->getMessage() : null
            ];
        }
    }

    private function tableExists(PDO $db, string $table): bool
    {
        try {
            $stmt = $db->prepare('SHOW TABLES LIKE :table');
            $stmt->execute(['table' => $table]);
            return (bool) $stmt->fetchColumn();
        } catch (Exception $exception) {
            return false;
        }
    }

    private function serialiseActivity(array $activity): array
    {
        $timestamp = $activity['timestamp'] ?? null;

        return [
            'exists' => $activity['exists'] ?? false,
            'timestamp' => $timestamp instanceof DateTimeImmutable ? $timestamp->format(DateTimeImmutable::ATOM) : null,
            'age_hours' => $activity['age_hours'] ?? null,
            'error' => $activity['error'] ?? null
        ];
    }

    private function adjustStatus(string $currentStatus, array $check): string
    {
        $severity = $check['status'] ?? ($check['healthy'] ? 'healthy' : 'degraded');
        $order = ['healthy' => 0, 'degraded' => 1, 'critical' => 2];

        if (!array_key_exists($severity, $order)) {
            $severity = $check['healthy'] ? 'healthy' : 'degraded';
        }

        if (!array_key_exists($currentStatus, $order)) {
            $currentStatus = 'healthy';
        }

        return $order[$severity] > $order[$currentStatus] ? $severity : $currentStatus;
    }
}