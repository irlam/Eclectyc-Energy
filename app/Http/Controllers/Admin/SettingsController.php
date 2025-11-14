<?php
declare(strict_types=1);

/**
 * File: SettingsController.php
 * Path: app/Http/Controllers/Admin/SettingsController.php
 *
 * What this file does:
 * - Renders the System Settings page (/tools/settings)
 * - Loads system settings from the database and merges them with safe defaults
 * - Saves updates to settings (booleans, integers, strings)
 * - Resets individual settings back to known defaults
 * - Provides user feedback via flash messages
 *
 * Notes:
 * - Defaults are always shown even if the database has no rows yet
 * - Grouping keeps settings organised in the UI (e.g. "Import Throttling")
 *
 * Last updated (UK): 09/11/2025 12:18:09
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

    /**
     * Default settings (used for UI presence and reset behaviour)
     * Each default also carries its category and description for UI display.
     */
    private const DEFAULTS = [
        'import_throttle_enabled' => [
            'value'       => false,
            'type'        => 'boolean',
            'description' => 'Enable throttling to avoid timeouts during large imports',
            'is_editable' => true,
            'category'    => 'Import Throttling',
        ],
        'import_throttle_batch_size' => [
            'value'       => 100,
            'type'        => 'integer',
            'description' => 'Number of rows processed per batch',
            'is_editable' => true,
            'category'    => 'Import Throttling',
        ],
        'import_throttle_delay_ms' => [
            'value'       => 100,
            'type'        => 'integer',
            'description' => 'Delay in milliseconds between batches',
            'is_editable' => true,
            'category'    => 'Import Throttling',
        ],
        'import_max_execution_time' => [
            'value'       => 300,
            'type'        => 'integer',
            'description' => 'Maximum script execution time in seconds for imports',
            'is_editable' => true,
            'category'    => 'Import Throttling',
        ],
        'import_max_memory_mb' => [
            'value'       => 256,
            'type'        => 'integer',
            'description' => 'Maximum memory in MB available during import',
            'is_editable' => true,
            'category'    => 'Import Throttling',
        ],
    ];

    public function __construct(Twig $view, ?PDO $pdo)
    {
        $this->view = $view;
        $this->pdo = $pdo;
    }

    /**
     * Display System Settings page
     */
    public function index(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            return $this->view->render($response, 'tools/settings.twig', [
                'page_title' => 'System Settings',
                'error'      => 'Database connection unavailable.',
                'settings'   => [],
            ]);
        }

        $flash = $_SESSION['settings_flash'] ?? null;
        unset($_SESSION['settings_flash']);

        try {
            $settingsService = new SystemSettingsService($this->pdo);

            // Fetch rows from DB and merge with defaults so the UI always has complete data
            $dbSettings     = $settingsService->getAll();
            $mergedSettings = $this->mergeWithDefaults($dbSettings);

            // Group settings by category for the UI
            $groupedSettings = $this->groupSettings($mergedSettings);

            return $this->view->render($response, 'tools/settings.twig', [
                'page_title' => 'System Settings',
                'settings'   => $groupedSettings,
                'flash'      => $flash,
            ]);
        } catch (\Throwable $e) {
            error_log('Failed to load settings: ' . $e->getMessage());
            return $this->view->render($response, 'tools/settings.twig', [
                'page_title' => 'System Settings',
                'error'      => 'Failed to load settings: ' . $e->getMessage(),
                'settings'   => [],
            ]);
        }
    }

    /**
     * Save updates to settings
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
            $updatedCount    = 0;

            foreach ($data as $key => $value) {
                // Skip non-setting fields (add to this list if your form contains extra fields)
                if (in_array($key, ['csrf_token', 'submit'], true)) {
                    continue;
                }

                // Determine type (prefer controller defaults, otherwise infer from value/name)
                $type = $this->inferType($key, $value);

                // HTML checkbox sends 'on' when checked; ensure strict boolean persisted
                if ($type === 'boolean') {
                    $value = isset($data[$key]) && $data[$key] === 'on';
                }

                // Persist
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
        } catch (\Throwable $e) {
            error_log('Failed to update settings: ' . $e->getMessage());
            $this->setFlash('error', 'Failed to update settings: ' . $e->getMessage());
        }

        return $this->redirect($response, '/tools/settings');
    }

    /**
     * Reset a single setting back to its default
     */
    public function reset(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/tools/settings');
        }

        $data       = $request->getParsedBody() ?? [];
        $settingKey = $data['setting_key'] ?? null;

        if (!$settingKey) {
            $this->setFlash('error', 'Setting key is required.');
            return $this->redirect($response, '/tools/settings');
        }

        try {
            $settingsService = new SystemSettingsService($this->pdo);

            if (isset(self::DEFAULTS[$settingKey])) {
                $default = self::DEFAULTS[$settingKey];
                $ok = $settingsService->set($settingKey, $default['value'], $default['type']);
                if ($ok) {
                    $this->setFlash('success', "“{$settingKey}” has been reset to its default.");
                } else {
                    $this->setFlash('error', "Failed to reset “{$settingKey}”.");
                }
            } else {
                // If we don't know the default, best-effort: remove DB override entirely
                $stmt = $this->pdo->prepare('DELETE FROM system_settings WHERE setting_key = ?');
                $stmt->execute([$settingKey]);
                if ($stmt->rowCount() > 0) {
                    $this->setFlash('success', "“{$settingKey}” has been cleared.");
                } else {
                    $this->setFlash('warning', "No stored value found for “{$settingKey}”.");
                }
            }
        } catch (\Throwable $e) {
            error_log('Failed to reset setting: ' . $e->getMessage());
            $this->setFlash('error', 'Failed to reset setting: ' . $e->getMessage());
        }

        return $this->redirect($response, '/tools/settings');
    }

    /**
     * Merge DB settings with controller defaults so all expected keys are present
     * @param array<string,array{value:mixed,type:string,description:?string,is_editable:bool}> $dbSettings
     * @return array<string,array{value:mixed,type:string,description:?string,is_editable:bool,category:string}>
     */
    private function mergeWithDefaults(array $dbSettings): array
    {
        // Start with defaults
        $merged = [];
        foreach (self::DEFAULTS as $key => $meta) {
            $merged[$key] = [
                'value'       => $meta['value'],
                'type'        => $meta['type'],
                'description' => $meta['description'],
                'is_editable' => (bool) $meta['is_editable'],
                'category'    => $meta['category'],
            ];
        }

        // Overlay DB values (preserving extra metadata if present in DB)
        foreach ($dbSettings as $key => $row) {
            $merged[$key] = [
                'value'       => $row['value'],
                'type'        => $row['type'] ?? ($merged[$key]['type'] ?? 'string'),
                'description' => $row['description'] ?? ($merged[$key]['description'] ?? null),
                'is_editable' => isset($row['is_editable']) ? (bool) $row['is_editable'] : ($merged[$key]['is_editable'] ?? true),
                'category'    => $merged[$key]['category'] ?? $this->categoryForKey($key),
            ];
        }

        return $merged;
    }

    /**
     * Group settings by category for the template
     * @param array<string,array{value:mixed,type:string,description:?string,is_editable:bool,category:string}> $settings
     * @return array<string,array<string,array{value:mixed,type:string,description:?string,is_editable:bool}>>
     */
    private function groupSettings(array $settings): array
    {
        $grouped = [];
        foreach ($settings as $key => $meta) {
            $category = $meta['category'] ?? $this->categoryForKey($key);
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][$key] = [
                'value'       => $meta['value'],
                'type'        => $meta['type'],
                'description' => $meta['description'] ?? null,
                'is_editable' => (bool) $meta['is_editable'],
            ];
        }

        // Sort categories and keys for a tidy UI
        ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);
        foreach ($grouped as &$items) {
            ksort($items, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $grouped;
    }

    /**
     * Guess a category for unknown keys
     */
    private function categoryForKey(string $key): string
    {
        if (str_starts_with($key, 'import_')) {
            return 'Import Throttling';
        }
        return 'General';
    }

    /**
     * Determine a setting type, preferring controller defaults when available
     */
    private function inferType(string $key, mixed $value): string
    {
        if (isset(self::DEFAULTS[$key])) {
            return self::DEFAULTS[$key]['type'];
        }

        $lower = is_string($value) ? strtolower($value) : $value;

        // Checkbox and boolean-like values
        if (str_contains($key, 'enabled') || $lower === 'on' || $lower === 'true' || $lower === 'false' || $lower === 1 || $lower === 0) {
            return 'boolean';
        }

        // Integer if numeric without decimals
        if (is_numeric($value) && (string)(int)$value === (string)$value) {
            return 'integer';
        }

        return 'string';
    }

    /**
     * Store a one-time flash message in session
     */
    private function setFlash(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['settings_flash'] = [
            'type'    => $type,
            'message' => $message,
        ];
    }

    /**
     * Return an HTTP redirect response
     */
    private function redirect(Response $response, string $url): Response
    {
        return $response
            ->withHeader('Location', $url)
            ->withStatus(302);
    }
}
