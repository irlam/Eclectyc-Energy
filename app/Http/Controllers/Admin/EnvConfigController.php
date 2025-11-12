<?php
/**
 * eclectyc-energy/app/Http/Controllers/Admin/EnvConfigController.php
 * Controller for managing .env file configuration through GUI
 * Last updated: 2025-11-12
 */

namespace App\Http\Controllers\Admin;

use App\Domain\Settings\EnvConfigService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class EnvConfigController
{
    private Twig $view;

    public function __construct(Twig $view)
    {
        $this->view = $view;
    }

    /**
     * Display environment configuration page
     */
    public function index(Request $request, Response $response): Response
    {
        $flash = $_SESSION['env_config_flash'] ?? null;
        unset($_SESSION['env_config_flash']);

        try {
            $envService = new EnvConfigService();
            $settings = $envService->getAllGrouped();
            $isWritable = $envService->isWritable();
            $envPath = $envService->getEnvPath();

            return $this->view->render($response, 'admin/env_config.twig', [
                'page_title' => 'Environment Configuration',
                'settings' => $settings,
                'is_writable' => $isWritable,
                'env_path' => $envPath,
                'flash' => $flash,
            ]);
            
        } catch (\Throwable $e) {
            error_log('Failed to load environment configuration: ' . $e->getMessage());
            
            return $this->view->render($response, 'admin/env_config.twig', [
                'page_title' => 'Environment Configuration',
                'error' => 'Failed to load environment configuration: ' . $e->getMessage(),
                'settings' => [],
                'is_writable' => false,
            ]);
        }
    }

    /**
     * Update environment configuration
     */
    public function update(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];

        try {
            $envService = new EnvConfigService();
            
            // Filter out non-setting fields
            $settings = [];
            foreach ($data as $key => $value) {
                if (in_array($key, ['csrf_token', 'submit'], true)) {
                    continue;
                }
                
                // Handle checkboxes (boolean values)
                if (is_array(EnvConfigService::getSettingsMetadata()) 
                    && isset(EnvConfigService::getSettingsMetadata()[$key])
                    && EnvConfigService::getSettingsMetadata()[$key]['type'] === 'boolean') {
                    $settings[$key] = isset($data[$key]) && $data[$key] === 'on';
                } else {
                    $settings[$key] = $value;
                }
            }

            $envService->update($settings);

            $this->setFlash('success', 'Environment configuration updated successfully! Changes will take effect on next page load.');
            
        } catch (\Throwable $e) {
            error_log('Failed to update environment configuration: ' . $e->getMessage());
            $this->setFlash('error', 'Failed to update configuration: ' . $e->getMessage());
        }

        return $this->redirect($response, '/admin/env-config');
    }

    /**
     * Test .env file permissions
     */
    public function testPermissions(Request $request, Response $response): Response
    {
        try {
            $envService = new EnvConfigService();
            $isWritable = $envService->isWritable();
            $envPath = $envService->getEnvPath();
            
            $permissions = substr(sprintf('%o', fileperms($envPath)), -4);
            $owner = posix_getpwuid(fileowner($envPath));
            $group = posix_getgrgid(filegroup($envPath));

            $response->getBody()->write(json_encode([
                'success' => true,
                'writable' => $isWritable,
                'path' => $envPath,
                'permissions' => $permissions,
                'owner' => $owner['name'] ?? 'unknown',
                'group' => $group['name'] ?? 'unknown',
                'message' => $isWritable 
                    ? 'File is writable and can be updated.' 
                    : 'File is NOT writable. Please check permissions.',
            ]));
            
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]));
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Download backup of current .env file
     */
    public function downloadBackup(Request $request, Response $response): Response
    {
        try {
            $envService = new EnvConfigService();
            $envPath = $envService->getEnvPath();
            
            if (!file_exists($envPath)) {
                throw new \Exception('.env file not found');
            }

            $content = file_get_contents($envPath);
            $filename = '.env.backup.' . date('Y-m-d_H-i-s');

            $response->getBody()->write($content);
            
            return $response
                ->withHeader('Content-Type', 'text/plain')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withHeader('Content-Length', strlen($content));
                
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Failed to create backup: ' . $e->getMessage());
            return $this->redirect($response, '/admin/env-config');
        }
    }

    /**
     * Store a one-time flash message in session
     */
    private function setFlash(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['env_config_flash'] = [
            'type' => $type,
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
