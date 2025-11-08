<?php
/**
 * eclectyc-energy/app/Domain/Settings/SystemSettingsService.php
 * Service for managing system-wide settings
 * Last updated: 2025-11-08
 */

namespace App\Domain\Settings;

use PDO;

class SystemSettingsService
{
    private PDO $pdo;
    private array $cache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get a setting value by key
     */
    public function get(string $key, $default = null)
    {
        // Check cache first
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $stmt = $this->pdo->prepare('
            SELECT setting_value, setting_type FROM system_settings WHERE setting_key = ?
        ');
        $stmt->execute([$key]);
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$setting) {
            return $default;
        }

        $value = $this->castValue($setting['setting_value'], $setting['setting_type']);
        $this->cache[$key] = $value;
        
        return $value;
    }

    /**
     * Set a setting value
     */
    public function set(string $key, $value, string $type = 'string'): bool
    {
        $stringValue = $this->valueToString($value, $type);
        
        $stmt = $this->pdo->prepare('
            INSERT INTO system_settings (setting_key, setting_value, setting_type)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type)
        ');
        
        $result = $stmt->execute([$key, $stringValue, $type]);
        
        // Clear cache for this key
        unset($this->cache[$key]);
        
        return $result;
    }

    /**
     * Get all settings
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM system_settings ORDER BY setting_key');
        $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['setting_key']] = [
                'value' => $this->castValue($setting['setting_value'], $setting['setting_type']),
                'type' => $setting['setting_type'],
                'description' => $setting['description'],
                'is_editable' => (bool) $setting['is_editable'],
            ];
        }
        
        return $result;
    }

    /**
     * Get import throttle settings
     */
    public function getImportThrottleSettings(): array
    {
        return [
            'enabled' => $this->get('import_throttle_enabled', false),
            'batch_size' => $this->get('import_throttle_batch_size', 100),
            'delay_ms' => $this->get('import_throttle_delay_ms', 100),
            'max_execution_time' => $this->get('import_max_execution_time', 300),
            'max_memory_mb' => $this->get('import_max_memory_mb', 256),
        ];
    }

    /**
     * Cast value from string based on type
     */
    private function castValue(?string $value, string $type)
    {
        if ($value === null) {
            return null;
        }

        switch ($type) {
            case 'boolean':
                return in_array(strtolower($value), ['true', '1', 'yes', 'on'], true);
            case 'integer':
                return (int) $value;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    /**
     * Convert value to string for storage
     */
    private function valueToString($value, string $type): string
    {
        switch ($type) {
            case 'boolean':
                return $value ? 'true' : 'false';
            case 'integer':
                return (string) (int) $value;
            case 'json':
                return json_encode($value);
            default:
                return (string) $value;
        }
    }
}
