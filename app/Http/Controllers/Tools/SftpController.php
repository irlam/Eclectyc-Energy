<?php
/**
 * eclectyc-energy/app/Http/Controllers/Tools/SftpController.php
 * Controller for managing SFTP configurations
 * Last updated: 2025-11-08
 */

namespace App\Http\Controllers\Tools;

use App\Domain\Sftp\SftpService;
use App\Domain\Ingestion\ImportJobService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class SftpController
{
    private Twig $view;
    private ?PDO $pdo;

    public function __construct(Twig $view, ?PDO $pdo)
    {
        $this->view = $view;
        $this->pdo = $pdo;
    }

    /**
     * List all SFTP configurations
     */
    public function index(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            return $this->view->render($response, 'tools/sftp/index.twig', [
                'page_title' => 'SFTP Configurations',
                'error' => 'Database connection unavailable.',
                'configurations' => [],
            ]);
        }

        $flash = $_SESSION['sftp_flash'] ?? null;
        unset($_SESSION['sftp_flash']);

        $service = new SftpService($this->pdo);
        $configurations = $service->getAllConfigurations();

        return $this->view->render($response, 'tools/sftp/index.twig', [
            'page_title' => 'SFTP Configurations',
            'configurations' => $configurations,
            'flash' => $flash,
        ]);
    }

    /**
     * Show create form
     */
    public function create(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'tools/sftp/create.twig', [
            'page_title' => 'New SFTP Configuration',
        ]);
    }

    /**
     * Store new configuration
     */
    public function store(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/tools/sftp');
        }

        $data = $request->getParsedBody() ?? [];
        $errors = $this->validateConfiguration($data);

        if (!empty($errors)) {
            $this->setFlash('error', implode(', ', $errors));
            return $this->redirect($response, '/tools/sftp/create');
        }

        try {
            $service = new SftpService($this->pdo);
            $id = $service->createConfiguration($data);
            
            $this->setFlash('success', 'SFTP configuration created successfully.');
            return $this->redirect($response, '/tools/sftp');
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Failed to create configuration: ' . $e->getMessage());
            return $this->redirect($response, '/tools/sftp/create');
        }
    }

    /**
     * Show edit form
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/tools/sftp');
        }

        $id = (int) $args['id'];
        $service = new SftpService($this->pdo);
        $configuration = $service->getConfiguration($id);

        if (!$configuration) {
            $this->setFlash('error', 'Configuration not found.');
            return $this->redirect($response, '/tools/sftp');
        }

        // Don't send actual password to frontend
        $configuration['password'] = '';

        return $this->view->render($response, 'tools/sftp/edit.twig', [
            'page_title' => 'Edit SFTP Configuration',
            'configuration' => $configuration,
        ]);
    }

    /**
     * Update configuration
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/tools/sftp');
        }

        $id = (int) $args['id'];
        $data = $request->getParsedBody() ?? [];
        $errors = $this->validateConfiguration($data, true);

        if (!empty($errors)) {
            $this->setFlash('error', implode(', ', $errors));
            return $this->redirect($response, "/tools/sftp/{$id}/edit");
        }

        try {
            $service = new SftpService($this->pdo);
            $service->updateConfiguration($id, $data);
            
            $this->setFlash('success', 'SFTP configuration updated successfully.');
            return $this->redirect($response, '/tools/sftp');
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Failed to update configuration: ' . $e->getMessage());
            return $this->redirect($response, "/tools/sftp/{$id}/edit");
        }
    }

    /**
     * Delete configuration
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/tools/sftp');
        }

        $id = (int) $args['id'];

        try {
            $service = new SftpService($this->pdo);
            $service->deleteConfiguration($id);
            
            $this->setFlash('success', 'SFTP configuration deleted successfully.');
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Failed to delete configuration: ' . $e->getMessage());
        }

        return $this->redirect($response, '/tools/sftp');
    }

    /**
     * Test SFTP connection
     */
    public function testConnection(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Database connection unavailable.',
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $id = (int) $args['id'];

        try {
            $service = new SftpService($this->pdo);
            $result = $service->testConnection($id);
            
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage(),
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * List files from SFTP server
     */
    public function listFiles(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/tools/sftp');
        }

        $id = (int) $args['id'];

        try {
            $service = new SftpService($this->pdo);
            $files = $service->listFiles($id);
            $configuration = $service->getConfiguration($id);
            
            return $this->view->render($response, 'tools/sftp/files.twig', [
                'page_title' => 'SFTP Files',
                'configuration' => $configuration,
                'files' => $files,
            ]);
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Failed to list files: ' . $e->getMessage());
            return $this->redirect($response, '/tools/sftp');
        }
    }

    /**
     * Import a file from SFTP
     */
    public function importFile(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/tools/sftp');
        }

        $id = (int) $args['id'];
        $data = $request->getParsedBody() ?? [];
        $filename = $data['filename'] ?? null;

        if (!$filename) {
            $this->setFlash('error', 'Filename is required.');
            return $this->redirect($response, "/tools/sftp/{$id}/files");
        }

        try {
            $sftpService = new SftpService($this->pdo);
            $configuration = $sftpService->getConfiguration($id);
            
            if (!$configuration) {
                throw new \Exception('Configuration not found');
            }

            // Download file from SFTP
            $localPath = $sftpService->downloadFile($id, $filename);
            
            // Create import job
            $importService = new ImportJobService($this->pdo);
            $batchId = $importService->createJob(
                $filename,
                $localPath,
                $configuration['import_type'],
                $this->currentUserId(),
                false // not a dry run
            );
            
            // Delete from SFTP if configured
            if ($configuration['delete_after_import']) {
                $sftpService->deleteRemoteFile($id, $filename);
            }
            
            $this->setFlash('success', 'File imported successfully. Job ID: ' . $batchId);
            return $this->redirect($response, '/admin/imports/status/' . $batchId);
            
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Failed to import file: ' . $e->getMessage());
            return $this->redirect($response, "/tools/sftp/{$id}/files");
        }
    }

    /**
     * Validate configuration data
     */
    private function validateConfiguration(array $data, bool $isUpdate = false): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors[] = 'Name is required';
        }

        if (empty($data['host'])) {
            $errors[] = 'Host is required';
        }

        if (empty($data['username'])) {
            $errors[] = 'Username is required';
        }

        // Password is only required for new configurations
        if (!$isUpdate && empty($data['password']) && empty($data['private_key_path'])) {
            $errors[] = 'Either password or private key path is required';
        }

        if (!empty($data['port']) && (!is_numeric($data['port']) || $data['port'] < 1 || $data['port'] > 65535)) {
            $errors[] = 'Port must be between 1 and 65535';
        }

        return $errors;
    }

    private function redirect(Response $response, string $path): Response
    {
        return $response->withHeader('Location', $path)->withStatus(302);
    }

    private function setFlash(string $type, string $message): void
    {
        $_SESSION['sftp_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    private function currentUserId(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }
}
