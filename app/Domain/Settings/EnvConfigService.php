<?php
/**
 * eclectyc-energy/app/Domain/Settings/EnvConfigService.php
 * Service for managing .env file configuration through the GUI
 * Last updated: 2025-11-12
 */

namespace App\Domain\Settings;

use Exception;

class EnvConfigService
{
    private string $envPath;
    private array $envData = [];
    
    // Define all .env settings with categories and metadata
    private const ENV_SETTINGS = [
        // Application Settings
        'APP_ENV' => [
            'category' => 'Application',
            'description' => 'Application environment (production, development, staging)',
            'type' => 'select',
            'options' => ['production', 'development', 'staging'],
            'required' => true,
        ],
        'APP_DEBUG' => [
            'category' => 'Application',
            'description' => 'Enable debug mode (true/false)',
            'type' => 'boolean',
            'required' => true,
        ],
        'APP_URL' => [
            'category' => 'Application',
            'description' => 'Base URL of the application',
            'type' => 'url',
            'required' => true,
        ],
        'APP_TIMEZONE' => [
            'category' => 'Application',
            'description' => 'Application timezone',
            'type' => 'text',
            'required' => true,
        ],
        
        // Database Configuration
        'DB_HOST' => [
            'category' => 'Database',
            'description' => 'Database host address',
            'type' => 'text',
            'required' => true,
        ],
        'DB_PORT' => [
            'category' => 'Database',
            'description' => 'Database port',
            'type' => 'number',
            'required' => true,
        ],
        'DB_DATABASE' => [
            'category' => 'Database',
            'description' => 'Database name',
            'type' => 'text',
            'required' => true,
        ],
        'DB_USERNAME' => [
            'category' => 'Database',
            'description' => 'Database username',
            'type' => 'text',
            'required' => true,
        ],
        'DB_PASSWORD' => [
            'category' => 'Database',
            'description' => 'Database password',
            'type' => 'password',
            'required' => true,
            'sensitive' => true,
        ],
        'DB_CHARSET' => [
            'category' => 'Database',
            'description' => 'Database character set',
            'type' => 'text',
            'required' => false,
        ],
        
        // Logging
        'LOG_LEVEL' => [
            'category' => 'Logging',
            'description' => 'Logging level',
            'type' => 'select',
            'options' => ['DEBUG', 'INFO', 'WARNING', 'ERROR', 'CRITICAL'],
            'required' => false,
        ],
        'LOG_PATH' => [
            'category' => 'Logging',
            'description' => 'Path to log files',
            'type' => 'text',
            'required' => false,
        ],
        
        // Mail Configuration
        'MAIL_HOST' => [
            'category' => 'Email',
            'description' => 'SMTP server hostname',
            'type' => 'text',
            'required' => false,
        ],
        'MAIL_PORT' => [
            'category' => 'Email',
            'description' => 'SMTP server port',
            'type' => 'number',
            'required' => false,
        ],
        'MAIL_USERNAME' => [
            'category' => 'Email',
            'description' => 'SMTP username',
            'type' => 'text',
            'required' => false,
        ],
        'MAIL_PASSWORD' => [
            'category' => 'Email',
            'description' => 'SMTP password',
            'type' => 'password',
            'required' => false,
            'sensitive' => true,
        ],
        'MAIL_ENCRYPTION' => [
            'category' => 'Email',
            'description' => 'Email encryption method',
            'type' => 'select',
            'options' => ['tls', 'ssl', 'none'],
            'required' => false,
        ],
        'MAIL_FROM_ADDRESS' => [
            'category' => 'Email',
            'description' => 'Default sender email address',
            'type' => 'email',
            'required' => false,
        ],
        'MAIL_FROM_NAME' => [
            'category' => 'Email',
            'description' => 'Default sender name',
            'type' => 'text',
            'required' => false,
        ],
        
        // SFTP Configuration
        'SFTP_HOST' => [
            'category' => 'SFTP Export',
            'description' => 'SFTP server hostname',
            'type' => 'text',
            'required' => false,
        ],
        'SFTP_PORT' => [
            'category' => 'SFTP Export',
            'description' => 'SFTP server port',
            'type' => 'number',
            'required' => false,
        ],
        'SFTP_USERNAME' => [
            'category' => 'SFTP Export',
            'description' => 'SFTP username',
            'type' => 'text',
            'required' => false,
        ],
        'SFTP_PASSWORD' => [
            'category' => 'SFTP Export',
            'description' => 'SFTP password',
            'type' => 'password',
            'required' => false,
            'sensitive' => true,
        ],
        'SFTP_PATH' => [
            'category' => 'SFTP Export',
            'description' => 'SFTP remote path',
            'type' => 'text',
            'required' => false,
        ],
        'SFTP_PRIVATE_KEY' => [
            'category' => 'SFTP Export',
            'description' => 'Path to SSH private key',
            'type' => 'text',
            'required' => false,
        ],
        'SFTP_PASSPHRASE' => [
            'category' => 'SFTP Export',
            'description' => 'SSH key passphrase',
            'type' => 'password',
            'required' => false,
            'sensitive' => true,
        ],
        'SFTP_TIMEOUT' => [
            'category' => 'SFTP Export',
            'description' => 'Connection timeout in seconds',
            'type' => 'number',
            'required' => false,
        ],
        
        // Session Configuration
        'SESSION_LIFETIME' => [
            'category' => 'Session',
            'description' => 'Session lifetime in minutes',
            'type' => 'number',
            'required' => false,
        ],
        'SESSION_SECURE' => [
            'category' => 'Session',
            'description' => 'Use secure cookies (HTTPS only)',
            'type' => 'boolean',
            'required' => false,
        ],
        'SESSION_HTTPONLY' => [
            'category' => 'Session',
            'description' => 'Use HTTP-only cookies',
            'type' => 'boolean',
            'required' => false,
        ],
        
        // API Keys
        'API_KEY' => [
            'category' => 'API Keys',
            'description' => 'General API key',
            'type' => 'password',
            'required' => false,
            'sensitive' => true,
        ],
        'API_SECRET' => [
            'category' => 'API Keys',
            'description' => 'General API secret',
            'type' => 'password',
            'required' => false,
            'sensitive' => true,
        ],
        
        // Alerts Configuration
        'ADMIN_EMAIL' => [
            'category' => 'Alerts & Monitoring',
            'description' => 'Administrator email address',
            'type' => 'email',
            'required' => false,
        ],
        'ALERT_ENABLED' => [
            'category' => 'Alerts & Monitoring',
            'description' => 'Enable system alerts',
            'type' => 'boolean',
            'required' => false,
        ],
        'HEALTH_MAX_EXPORT_HOURS' => [
            'category' => 'Alerts & Monitoring',
            'description' => 'Maximum hours before export alert',
            'type' => 'number',
            'required' => false,
        ],
        'HEALTH_MAX_IMPORT_HOURS' => [
            'category' => 'Alerts & Monitoring',
            'description' => 'Maximum hours before import alert',
            'type' => 'number',
            'required' => false,
        ],
        
        // External Data Sources
        'WEATHER_API_KEY' => [
            'category' => 'External APIs',
            'description' => 'Weather API key',
            'type' => 'password',
            'required' => false,
            'sensitive' => true,
        ],
        'WEATHER_API_URL' => [
            'category' => 'External APIs',
            'description' => 'Weather API URL',
            'type' => 'url',
            'required' => false,
        ],
        'CARBON_API_KEY' => [
            'category' => 'External APIs',
            'description' => 'Carbon Intensity API key',
            'type' => 'password',
            'required' => false,
            'sensitive' => true,
        ],
        'CARBON_API_URL' => [
            'category' => 'External APIs',
            'description' => 'Carbon Intensity API URL',
            'type' => 'url',
            'required' => false,
        ],
        
        // AI Insights Configuration
        'OPENAI_API_KEY' => [
            'category' => 'AI Insights',
            'description' => 'OpenAI API Key',
            'type' => 'password',
            'required' => false,
            'sensitive' => true,
        ],
        'OPENAI_MODEL' => [
            'category' => 'AI Insights',
            'description' => 'OpenAI model name',
            'type' => 'text',
            'required' => false,
        ],
        'ANTHROPIC_API_KEY' => [
            'category' => 'AI Insights',
            'description' => 'Anthropic API Key',
            'type' => 'password',
            'required' => false,
            'sensitive' => true,
        ],
        'ANTHROPIC_MODEL' => [
            'category' => 'AI Insights',
            'description' => 'Anthropic model name',
            'type' => 'text',
            'required' => false,
        ],
        'GOOGLE_AI_API_KEY' => [
            'category' => 'AI Insights',
            'description' => 'Google AI API Key',
            'type' => 'password',
            'required' => false,
            'sensitive' => true,
        ],
        'GOOGLE_MODEL' => [
            'category' => 'AI Insights',
            'description' => 'Google AI model name',
            'type' => 'text',
            'required' => false,
        ],
        'AZURE_OPENAI_API_KEY' => [
            'category' => 'AI Insights',
            'description' => 'Azure OpenAI API Key',
            'type' => 'password',
            'required' => false,
            'sensitive' => true,
        ],
        'AZURE_OPENAI_ENDPOINT' => [
            'category' => 'AI Insights',
            'description' => 'Azure OpenAI Endpoint URL',
            'type' => 'url',
            'required' => false,
        ],
        'AZURE_OPENAI_MODEL' => [
            'category' => 'AI Insights',
            'description' => 'Azure OpenAI model name',
            'type' => 'text',
            'required' => false,
        ],
    ];

    public function __construct(?string $envPath = null)
    {
        $this->envPath = $envPath ?? BASE_PATH . '/.env';
        $this->load();
    }

    /**
     * Load .env file into memory
     */
    private function load(): void
    {
        if (!file_exists($this->envPath)) {
            throw new Exception('.env file not found at: ' . $this->envPath);
        }

        $lines = file($this->envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                $value = trim($value, '"\'');
                
                $this->envData[$key] = $value;
            }
        }
    }

    /**
     * Get all environment settings grouped by category
     */
    public function getAllGrouped(): array
    {
        $grouped = [];
        
        foreach (self::ENV_SETTINGS as $key => $metadata) {
            $category = $metadata['category'];
            
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            
            $grouped[$category][$key] = array_merge($metadata, [
                'value' => $this->envData[$key] ?? '',
                'is_editable' => true,
            ]);
        }
        
        return $grouped;
    }

    /**
     * Get a single environment value
     */
    public function get(string $key): ?string
    {
        return $this->envData[$key] ?? null;
    }

    /**
     * Update environment values and write to .env file
     */
    public function update(array $values): bool
    {
        // Validate .env file is writable
        if (!is_writable($this->envPath)) {
            throw new Exception('.env file is not writable. Check file permissions.');
        }

        // Create backup
        $backupPath = $this->envPath . '.backup.' . date('Y-m-d_H-i-s');
        if (!copy($this->envPath, $backupPath)) {
            throw new Exception('Failed to create backup of .env file');
        }

        try {
            // Update values in memory
            foreach ($values as $key => $value) {
                if (isset(self::ENV_SETTINGS[$key])) {
                    // Process value based on type
                    $type = self::ENV_SETTINGS[$key]['type'] ?? 'text';
                    
                    if ($type === 'boolean') {
                        $value = $value ? 'true' : 'false';
                    }
                    
                    $this->envData[$key] = $value;
                }
            }

            // Write to file
            $content = $this->generateEnvContent();
            
            if (file_put_contents($this->envPath, $content) === false) {
                throw new Exception('Failed to write to .env file');
            }

            // Reload to verify
            $this->load();
            
            return true;
            
        } catch (Exception $e) {
            // Restore backup on failure
            copy($backupPath, $this->envPath);
            throw new Exception('Failed to update .env file: ' . $e->getMessage());
        }
    }

    /**
     * Generate .env file content from current data
     */
    private function generateEnvContent(): string
    {
        $content = "# eclectyc-energy/.env\n";
        $content .= "# Application Environment Configuration\n";
        $content .= "# Last updated: " . date('d/m/Y H:i:s') . "\n\n";

        $currentCategory = null;
        
        foreach (self::ENV_SETTINGS as $key => $metadata) {
            $category = $metadata['category'];
            
            // Add category header
            if ($category !== $currentCategory) {
                $content .= "\n# {$category}\n";
                $currentCategory = $category;
            }
            
            $value = $this->envData[$key] ?? '';
            
            // Quote values that contain spaces or special characters
            if (strpos($value, ' ') !== false || strpos($value, '#') !== false) {
                $value = '"' . $value . '"';
            }
            
            $content .= "{$key}={$value}\n";
        }

        return $content;
    }

    /**
     * Get metadata for all settings
     */
    public static function getSettingsMetadata(): array
    {
        return self::ENV_SETTINGS;
    }

    /**
     * Test .env file writability
     */
    public function isWritable(): bool
    {
        return is_writable($this->envPath);
    }

    /**
     * Get .env file path
     */
    public function getEnvPath(): string
    {
        return $this->envPath;
    }
}
