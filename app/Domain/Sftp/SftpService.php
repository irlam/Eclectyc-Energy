<?php
/**
 * eclectyc-energy/app/Domain/Sftp/SftpService.php
 * Service for managing SFTP connections and file transfers
 * Last updated: 2025-11-08
 */

namespace App\Domain\Sftp;

use phpseclib3\Net\SFTP;
use PDO;
use Exception;

class SftpService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get SFTP configuration by ID
     */
    public function getConfiguration(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM sftp_configurations WHERE id = ? AND is_active = 1
        ');
        $stmt->execute([$id]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            return null;
        }
        
        // Decrypt password if present
        if (!empty($config['password'])) {
            $config['password'] = $this->decryptPassword($config['password']);
        }
        
        return $config;
    }

    /**
     * Get all active SFTP configurations
     */
    public function getAllConfigurations(): array
    {
        $stmt = $this->pdo->query('
            SELECT id, name, host, port, username, remote_directory, 
                   file_pattern, import_type, auto_import, is_active,
                   last_connection_at, last_error
            FROM sftp_configurations 
            WHERE is_active = 1
            ORDER BY name ASC
        ');
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Create SFTP configuration
     */
    public function createConfiguration(array $data): int
    {
        // Encrypt password if provided
        if (!empty($data['password'])) {
            $data['password'] = $this->encryptPassword($data['password']);
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO sftp_configurations (
                name, host, port, username, password, private_key_path,
                remote_directory, file_pattern, import_type, auto_import,
                delete_after_import, is_active
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $data['name'],
            $data['host'],
            $data['port'] ?? 22,
            $data['username'],
            $data['password'] ?? null,
            $data['private_key_path'] ?? null,
            $data['remote_directory'] ?? '/',
            $data['file_pattern'] ?? '*.csv',
            $data['import_type'] ?? 'hh',
            !empty($data['auto_import']) ? 1 : 0,
            !empty($data['delete_after_import']) ? 1 : 0,
            !empty($data['is_active']) ? 1 : 0,
        ]);
        
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update SFTP configuration
     */
    public function updateConfiguration(int $id, array $data): bool
    {
        // Encrypt password if provided and not empty
        if (!empty($data['password'])) {
            $data['password'] = $this->encryptPassword($data['password']);
        } else {
            unset($data['password']); // Don't update if empty
        }

        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if (in_array($key, ['name', 'host', 'port', 'username', 'password', 'private_key_path', 
                                'remote_directory', 'file_pattern', 'import_type', 'auto_import',
                                'delete_after_import', 'is_active'])) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        
        $stmt = $this->pdo->prepare('
            UPDATE sftp_configurations 
            SET ' . implode(', ', $fields) . '
            WHERE id = ?
        ');
        
        return $stmt->execute($values);
    }

    /**
     * Delete SFTP configuration
     */
    public function deleteConfiguration(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM sftp_configurations WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Test SFTP connection
     */
    public function testConnection(int $configId): array
    {
        $config = $this->getConfiguration($configId);
        
        if (!$config) {
            return ['success' => false, 'message' => 'Configuration not found'];
        }

        try {
            $sftp = new SFTP($config['host'], $config['port']);
            
            // Authenticate
            $authenticated = false;
            if (!empty($config['private_key_path']) && file_exists($config['private_key_path'])) {
                $key = file_get_contents($config['private_key_path']);
                $authenticated = $sftp->login($config['username'], $key);
            } elseif (!empty($config['password'])) {
                $authenticated = $sftp->login($config['username'], $config['password']);
            }
            
            if (!$authenticated) {
                throw new Exception('Authentication failed');
            }
            
            // Try to change to remote directory
            if (!$sftp->chdir($config['remote_directory'])) {
                throw new Exception('Cannot access remote directory: ' . $config['remote_directory']);
            }
            
            // Update last connection timestamp
            $this->updateLastConnection($configId, true);
            
            return [
                'success' => true,
                'message' => 'Connection successful',
                'remote_directory' => $config['remote_directory'],
            ];
            
        } catch (Exception $e) {
            // Update last error
            $this->updateLastConnection($configId, false, $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * List files matching pattern in remote directory
     */
    public function listFiles(int $configId): array
    {
        $config = $this->getConfiguration($configId);
        
        if (!$config) {
            throw new Exception('Configuration not found');
        }

        try {
            $sftp = new SFTP($config['host'], $config['port']);
            
            // Authenticate
            if (!empty($config['private_key_path']) && file_exists($config['private_key_path'])) {
                $key = file_get_contents($config['private_key_path']);
                $sftp->login($config['username'], $key);
            } elseif (!empty($config['password'])) {
                $sftp->login($config['username'], $config['password']);
            }
            
            $sftp->chdir($config['remote_directory']);
            $allFiles = $sftp->nlist();
            
            // Filter files by pattern
            $pattern = str_replace('*', '.*', $config['file_pattern']);
            $pattern = '/^' . $pattern . '$/i';
            
            $matchedFiles = [];
            foreach ($allFiles as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                
                if (preg_match($pattern, $file)) {
                    $stat = $sftp->stat($file);
                    $matchedFiles[] = [
                        'name' => $file,
                        'size' => $stat['size'] ?? 0,
                        'modified' => $stat['mtime'] ?? null,
                    ];
                }
            }
            
            $this->updateLastConnection($configId, true);
            
            return $matchedFiles;
            
        } catch (Exception $e) {
            $this->updateLastConnection($configId, false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Download a file from SFTP to local storage
     */
    public function downloadFile(int $configId, string $remoteFilename): string
    {
        $config = $this->getConfiguration($configId);
        
        if (!$config) {
            throw new Exception('Configuration not found');
        }

        try {
            $sftp = new SFTP($config['host'], $config['port']);
            
            // Authenticate
            if (!empty($config['private_key_path']) && file_exists($config['private_key_path'])) {
                $key = file_get_contents($config['private_key_path']);
                $sftp->login($config['username'], $key);
            } elseif (!empty($config['password'])) {
                $sftp->login($config['username'], $config['password']);
            }
            
            $sftp->chdir($config['remote_directory']);
            
            // Create local storage directory
            $storageDir = dirname(__DIR__, 3) . '/storage/sftp';
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0755, true);
            }
            
            // Generate unique local filename
            $localFilename = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $remoteFilename);
            $localPath = $storageDir . '/' . $localFilename;
            
            // Download file
            if (!$sftp->get($remoteFilename, $localPath)) {
                throw new Exception('Failed to download file: ' . $remoteFilename);
            }
            
            $this->updateLastConnection($configId, true);
            
            return $localPath;
            
        } catch (Exception $e) {
            $this->updateLastConnection($configId, false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a file from SFTP server
     */
    public function deleteRemoteFile(int $configId, string $remoteFilename): bool
    {
        $config = $this->getConfiguration($configId);
        
        if (!$config) {
            throw new Exception('Configuration not found');
        }

        try {
            $sftp = new SFTP($config['host'], $config['port']);
            
            // Authenticate
            if (!empty($config['private_key_path']) && file_exists($config['private_key_path'])) {
                $key = file_get_contents($config['private_key_path']);
                $sftp->login($config['username'], $key);
            } elseif (!empty($config['password'])) {
                $sftp->login($config['username'], $config['password']);
            }
            
            $sftp->chdir($config['remote_directory']);
            
            return $sftp->delete($remoteFilename);
            
        } catch (Exception $e) {
            $this->updateLastConnection($configId, false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update last connection timestamp and error
     */
    private function updateLastConnection(int $configId, bool $success, ?string $error = null): void
    {
        if ($success) {
            $stmt = $this->pdo->prepare('
                UPDATE sftp_configurations 
                SET last_connection_at = NOW(), last_error = NULL
                WHERE id = ?
            ');
            $stmt->execute([$configId]);
        } else {
            $stmt = $this->pdo->prepare('
                UPDATE sftp_configurations 
                SET last_error = ?
                WHERE id = ?
            ');
            $stmt->execute([$error, $configId]);
        }
    }

    /**
     * Encrypt password for storage
     */
    private function encryptPassword(string $password): string
    {
        // Use a simple encryption - in production, use proper encryption with a secret key
        $key = $_ENV['APP_KEY'] ?? 'default-encryption-key-change-me';
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($password, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt password from storage
     */
    private function decryptPassword(string $encryptedPassword): string
    {
        $key = $_ENV['APP_KEY'] ?? 'default-encryption-key-change-me';
        $data = base64_decode($encryptedPassword);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
}
