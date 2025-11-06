<?php
/**
 * eclectyc-energy/app/Http/Controllers/Admin/ImportController.php
 * Admin-facing CSV import workflow with optional dry-run support.
 */

namespace App\Http\Controllers\Admin;

use App\Domain\Ingestion\CsvIngestionService;
use App\Domain\Ingestion\IngestionResult;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Ramsey\Uuid\Uuid;
use Slim\Views\Twig;

class ImportController
{
    private Twig $view;
    private ?PDO $pdo;

    public function __construct(Twig $view, ?PDO $pdo)
    {
        $this->view = $view;
        $this->pdo = $pdo;
    }

    public function index(Request $request, Response $response): Response
    {
        $flash = $_SESSION['import_flash'] ?? null;
        unset($_SESSION['import_flash']);

        return $this->view->render($response, 'admin/imports.twig', [
            'page_title' => 'Data Imports',
            'flash' => $flash,
        ]);
    }

    public function upload(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/imports');
        }

        $data = $request->getParsedBody() ?? [];
        $files = $request->getUploadedFiles();

        $format = strtolower($data['import_type'] ?? 'hh');
        $dryRun = isset($data['dry_run']);
        $uploadedFile = $files['csv_file'] ?? null;
        $errors = [];

        if (!$uploadedFile || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
            $errors[] = 'Please upload a CSV file.';
        }

        if (!in_array($format, ['hh', 'daily'], true)) {
            $errors[] = 'Invalid import type selected.';
        }

        if ($errors) {
            $this->setFlash('error', implode(' ', $errors), [
                'format' => $format,
                'dry_run' => $dryRun,
            ]);
            return $this->redirect($response, '/admin/imports');
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'import_');
        $uploadedFile->moveTo($tempPath);

        $service = new CsvIngestionService($this->pdo);
        $batchId = Uuid::uuid4()->toString();
        $summary = null;

        try {
            /** @var IngestionResult $result */
            $result = $service->ingestFromCsv($tempPath, $format, $batchId, $dryRun, $this->currentUserId());
            $summary = $result->toArray();
            $summary['filename'] = $uploadedFile->getClientFilename();
            $summary['dry_run'] = $dryRun;
            $summary['format'] = $format;
            $summary['errors'] = array_slice($result->getErrors(), 0, 10);

            $status = $result->hasErrors()
                ? 'warning'
                : ($dryRun ? 'info' : 'success');

            $message = $dryRun
                ? 'Dry-run completed. Review the results below.'
                : 'Import completed successfully.';

            if ($result->hasErrors()) {
                $message .= ' Some rows could not be imported.';
            }

            $this->setFlash($status, $message, $summary);
        } catch (\Throwable $exception) {
            $this->setFlash('error', 'Import failed: ' . $exception->getMessage());
        } finally {
            if ($tempPath && file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }

        return $this->redirect($response, '/admin/imports');
    }

    private function redirect(Response $response, string $path): Response
    {
        return $response->withHeader('Location', $path)->withStatus(302);
    }

    private function setFlash(string $type, string $message, ?array $payload = null): void
    {
        $_SESSION['import_flash'] = [
            'type' => $type,
            'message' => $message,
            'payload' => $payload,
        ];
    }

    private function currentUserId(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }
}
