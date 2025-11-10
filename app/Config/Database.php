<?php
/**
 * eclectyc-energy/app/config/database.php
 * Database configuration and connection helper
 * Last updated: 06/11/2024 14:45:00
 */

namespace App\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;
    
    /**
     * Get database connection
     */
    public static function getConnection(): ?PDO
    {
        if (self::$connection === null) {
            try {
                $config = self::getConfig();
                
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    $config['host'],
                    $config['port'],
                    $config['database'],
                    $config['charset']
                );
                
                self::$connection = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_PERSISTENT => true,  // Enable persistent connections to reuse connections
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                    ]
                );
                // Set connection timeout to prevent hanging connections
                self::$connection->setAttribute(PDO::ATTR_TIMEOUT, 30);
            } catch (PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                return null;
            }
        }
        
        return self::$connection;
    }
    
    /**
     * Get database configuration from environment
     */
    protected static function getConfig(): array
    {
        return [
            'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
            'port' => $_ENV['DB_PORT'] ?? '3306',
            'database' => $_ENV['DB_DATABASE'] ?? 'energy_platform',
            'username' => $_ENV['DB_USERNAME'] ?? 'root',
            'password' => $_ENV['DB_PASSWORD'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4'
        ];
    }
    
    /**
     * Close database connection
     */
    public static function closeConnection(): void
    {
        self::$connection = null;
    }
    
    /**
     * Execute a query with parameters
     */
    public static function execute(string $sql, array $params = []): ?PDOStatement
    {
        $connection = self::getConnection();
        if (!$connection) {
            return null;
        }
        
        try {
            $stmt = $connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log('Query execution failed: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Begin transaction
     */
    public static function beginTransaction(): bool
    {
        $connection = self::getConnection();
        return $connection ? $connection->beginTransaction() : false;
    }
    
    /**
     * Commit transaction
     */
    public static function commit(): bool
    {
        $connection = self::getConnection();
        return $connection ? $connection->commit() : false;
    }
    
    /**
     * Rollback transaction
     */
    public static function rollback(): bool
    {
        $connection = self::getConnection();
        return $connection ? $connection->rollBack() : false;
    }
}