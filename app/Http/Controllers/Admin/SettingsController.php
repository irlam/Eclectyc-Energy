<?php
/**
 * eclectyc-energy/app/Http/Controllers/Admin/SettingsController.php
 * Handles system settings management
 * Created: 09/11/2025
 */

namespace App\Http\Controllers\Admin;

use App\Domain\Settings\SystemSettingsService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class SettingsController
{
    private Twig $view;
    private ?PDO $pdo;

    public function __construct(Twig $view, ?PDO $pdo)
    {
        $this->view = $view;
        $this->pdo = $pdo;
    }

    /**
     * Display system settings page
     */
    public function index(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            return $this->view->render($response, 'tools/settings.twig', [
                'page_title' => 'System Settings',
                'error' => 'Database connection unavailable.',
                'settings' => [],
            ]);
        }

        $flash = $_SESSION['settings_flash'] ?? null;
        unset($_SESSION['settings_flash']);

        try {
            $settingsService = new SystemSettingsService($this->pdo);
            $allSettings = $settingsService->getAll();

            // Group settings by category
            $groupedSettings = $this->groupSettings($allSettings);

            return $this->view->render($response, 'tools/settings.twig', [
                'page_title' => 'System Settings',
                'settings' => $groupedSettings,
                'flash' => $flash,
            ]);
        } catch (\Exception $e) {
            error_log('Failed to load settings: ' . $e->getMessage());
            return $this->view->render($response, 'tools/settings.twig', [
                'page_title' => 'System Settings',
                'error' => 'Failed to load settings: ' . $e->getMessage(),
                'settings' => [],
            ]);
        }
    }

    /**
     * Update system settings
     */
    public function update(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/tools/settings');
        }

        $data = $request->getParsedBody() ?? [];

        try {
            $settingsService = new SystemSettingsService($this->pdo);
            $updatedCount = 0;

            foreach ($data as $key => $value) {
                // Skip CSRF tokens and non-setting fields
                if (in_array($key, ['csrf_token', 'submit'])) {
                    continue;
                }

                // Determine the type based on the setting key
                $type = $this->inferType($key, $value);

                // Convert checkbox values (checkbox sends 'on' or nothing)
                if ($type === 'boolean') {
                    $value = isset($data[$key]) && $data[$key] === 'on';
                }

                // Update the setting
                $success = $settingsService->set($key, $value, $type);
                
                if ($success) {
                    $updatedCount++;
                }
            }

            if ($updatedCount > 0) {
                $this->setFlash('success', "Successfully updated {$updatedCount} setting(s).");
            } else {
                $this->setFlash('warning', 'No settings were changed.');
            }

        } catch (\Exception $e) {
            error_log('Failed to update settings: ' . $e->getMessage());
            $this->setFlash('error', 'Failed to update settings: ' . $e->getMessage());
        }

        return $this->redirect($response, '/tools/settings');
    }

    /**
     * Reset settings to defaults
     */
    public function reset(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/tools/settings');
        }

        $data = $request->getParsedBody() ?? [];
        $settingKey = $data['setting_key'] ?? null;

        if (!$settingKey) {
            $this->setFlash('error', 'Setting key is required.');
            return $this->redirect($response, '/tools/settings');
        }

        try {
            // Get default values
            $defaults = $this->getDefaultValues();
            
            if (!isset($defaults[$settingKey])) {
                $this->setFlash('error', 'Unknown setting key.');
                return $this->redirect($response, '/tools/settings');
            }

            $default = $defaults[$settingKey];
            $settingsService = new SystemSettingsService($this->pdo);
            $success = $settingsService->set($settingKey, $default['value'], $default['type']);

            if ($success) {
                $this->setFlash('success', "Reset '{$settingKey}' to default value.");
            } else {
                $this->setFlash('error', 'Failed to reset setting.');
            }

        } catch (\Exception $e) {
            error_log('Failed to reset setting: ' . $e->getMessage());
            $this->setFlash('error', 'Failed to reset setting: ' . $e->getMessage());
        }

        return $this->redirect($response, '/tools/settings');
    }

    /**
     * Group settings by category
     */
    private function groupSettings(array $settings): array
    {
        $grouped = [
            'Import Throttling' => [],
            'Import Limits' => [],
            'Other' => [],
        ];

        foreach ($settings as $key => $setting) {
            if (strpos($key, 'import_throttle') === 0) {
                $grouped['Import Throttling'][$key] = $setting;
            } elseif (strpos($key, 'import_max') === 0) {
                $grouped['Import Limits'][$key] = $setting;
            } else {
                $grouped['Other'][$key] = $setting;
            }
        }

        // Remove empty categories
        return array_filter($grouped, function($category) {
            return !empty($category);
        });
    }

    /**
     * Infer setting type from key and value
     */
    private function inferType(string $key, $value): string
    {
        // Get type from existing setting if possible
        try {
            $settingsService = new SystemSettingsService($this->pdo);
            $stmt = $this->pdo->prepare('SELECT setting_type FROM system_settings WHERE setting_key = ?');
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return $result['setting_type'];
            }
        } catch (\Exception $e) {
            // Fall through to inference
        }

        // Infer from key name
        if (strpos($key, 'enabled') !== false || strpos($key, 'active') !== false) {
            return 'boolean';
        }

        if (strpos($key, 'count') !== false || strpos($key, 'size') !== false || 
            strpos($key, 'limit') !== false || strpos($key, 'max') !== false ||
            strpos($key, 'delay') !== false || strpos($key, 'time') !== false) {
            return 'integer';
        }

        return 'string';
    }

    /**
     * Get default values for all settings
     */
    private function getDefaultValues(): array
    {
        return [
            'import_throttle_enabled' => ['value' => false, 'type' => 'boolean'],
            'import_throttle_batch_size' => ['value' => 100, 'type' => 'integer'],
            'import_throttle_delay_ms' => ['value' => 100, 'type' => 'integer'],
            'import_max_execution_time' => ['value' => 300, 'type' => 'integer'],
            'import_max_memory_mb' => ['value' => 256, 'type' => 'integer'],
        ];
    }

    private function setFlash(string $type, string $message): void
    {
        $_SESSION['settings_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    private function redirect(Response $response, string $path): Response
    {
        return $response->withHeader('Location', $path)->withStatus(302);
    }
}
