<?php
/**
 * eclectyc-energy/app/http/Controllers/Api/HealthController.php
 * Health check controller for system status monitoring
 * Last updated: 06/11/2024 14:45:00
 */

namespace App\Http\Controllers\Api;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;

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
            'timestamp' => date('d/m/Y H:i:s'), // UK format
            'timezone' => date_default_timezone_get(),
            'environment' => $_ENV['APP_ENV'] ?? 'unknown',
            'checks' => []
        ];
        
        // Check database connectivity
        $dbCheck = $this->checkDatabase();
        $health['checks']['database'] = $dbCheck;
        
        if (!$dbCheck['healthy']) {
            $health['status'] = 'degraded';
        }
        
        // Check file system
        $fsCheck = $this->checkFileSystem();
        $health['checks']['filesystem'] = $fsCheck;
        
        if (!$fsCheck['healthy']) {
            $health['status'] = 'degraded';
        }
        
        // Check PHP version
        $phpCheck = $this->checkPhpVersion();
        $health['checks']['php'] = $phpCheck;
        
        // Memory usage
        $health['checks']['memory'] = [
            'healthy' => true,
            'current' => $this->formatBytes(memory_get_usage()),
            'peak' => $this->formatBytes(memory_get_peak_usage()),
            'limit' => ini_get('memory_limit')
        ];
        
        // Disk space
        $health['checks']['disk'] = $this->checkDiskSpace();
        
        // Application version
        $health['version'] = '1.0.0';
        $health['api_version'] = 'v1';
        
        // Response
        $response->getBody()->write(json_encode($health, JSON_PRETTY_PRINT));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($health['status'] === 'healthy' ? 200 : 503);
    }
    
    /**
     * Check database connectivity
     */
    protected function checkDatabase(): array
    {
        try {
            $db = $this->container->get('db');
            
            if (!$db) {
                return [
                    'healthy' => false,
                    'message' => 'Database connection not configured'
                ];
            }
            
            // Test with simple query
            $stmt = $db->query("SELECT 1");
            
            // Check if core tables exist
            $tables = [];
            $requiredTables = ['users', 'sites', 'meters', 'meter_readings'];
            
            $stmt = $db->query("SHOW TABLES");
            $existingTables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            foreach ($requiredTables as $table) {
                $tables[$table] = in_array($table, $existingTables);
            }
            
            $allTablesExist = !in_array(false, $tables, true);
            
            return [
                'healthy' => true,
                'connected' => true,
                'tables' => $tables,
                'all_tables_exist' => $allTablesExist,
                'message' => $allTablesExist ? 'Database operational' : 'Some tables missing - run migrations'
            ];
            
        } catch (\Exception $e) {
            return [
                'healthy' => false,
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
        
        // Check .env file
        $envFile = BASE_PATH . '/.env';
        $checks['env_exists'] = file_exists($envFile);
        $checks['env_readable'] = is_readable($envFile);
        
        if (!$checks['env_exists'] || !$checks['env_readable']) {
            $healthy = false;
        }
        
        return [
            'healthy' => $healthy,
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
        
        return [
            'healthy' => $percentUsed < 90,
            'free' => $this->formatBytes($free),
            'total' => $this->formatBytes($total),
            'used' => $this->formatBytes($used),
            'percent_used' => $percentUsed . '%',
            'message' => $percentUsed < 90 ? 'Disk space OK' : 'Low disk space warning'
        ];
    }
    
    /**
     * Format bytes to human readable
     */
    protected function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}