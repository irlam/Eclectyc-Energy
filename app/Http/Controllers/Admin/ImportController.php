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

    public function history(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            return $this->view->render($response, 'admin/imports_history.twig', [
                'page_title' => 'Import History',
                'error' => 'Database connection unavailable.',
                'entries' => [],
                'filters' => [
                    'status' => null,
                    'type' => null,
                    'limit' => 50,
                ],
            ]);
        }

        $query = $request->getQueryParams();
        $limit = isset($query['limit']) ? max(10, min(200, (int) $query['limit'])) : 50;
        $statusFilter = isset($query['status']) && $query['status'] !== '' ? strtolower($query['status']) : null;
        $typeFilter = isset($query['type']) && $query['type'] !== '' ? strtolower($query['type']) : null;

        $stmt = $this->pdo->prepare('SELECT a.id, a.user_id, a.new_values, a.created_at, u.name, u.email
            FROM audit_logs a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.action = :action
            ORDER BY a.created_at DESC
            LIMIT :limit');

        $stmt->bindValue(':action', 'import_csv');
        // fetch extra rows so filters still have data to work with
        $stmt->bindValue(':limit', max($limit, 150), PDO::PARAM_INT);
        $stmt->execute();

        $records = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $entries = [];
        foreach ($records as $row) {
            $payload = [];
            if (!empty($row['new_values'])) {
                $decoded = json_decode($row['new_values'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            $status = $this->deriveStatus($payload);
            $importType = strtolower($payload['format'] ?? $payload['meta']['format'] ?? 'unknown');

            if ($statusFilter && $status !== $statusFilter) {
                continue;
            }

            if ($typeFilter && $importType !== $typeFilter) {
                continue;
            }

            $entries[] = [
                'id' => (int) $row['id'],
                'user_name' => $row['name'] ?? null,
                'user_email' => $row['email'] ?? null,
                'created_at' => $row['created_at'],
                'completed_at' => $row['created_at'],
                'batch_id' => $payload['batch_id'] ?? null,
                'import_type' => $importType,
                'status' => $status,
                'total_records' => $payload['records_processed'] ?? 0,
                'imported_records' => $payload['records_imported'] ?? 0,
                'failed_records' => $payload['records_failed'] ?? 0,
                'dry_run' => (bool) ($payload['dry_run'] ?? false),
                'errors' => !empty($payload['errors']) && is_array($payload['errors']) ? $payload['errors'] : [],
                'meta' => isset($payload['meta']) && is_array($payload['meta']) ? $payload['meta'] : [],
            ];

            if (count($entries) >= $limit) {
                break;
            }
        }

        return $this->view->render($response, 'admin/imports_history.twig', [
            'page_title' => 'Import History',
            'entries' => $entries,
            'filters' => [
                'status' => $statusFilter,
                'type' => $typeFilter,
                'limit' => $limit,
            ],
            'error' => null,
        ]);
    }

    private function deriveStatus(array $payload): string
    {
        $imported = (int) ($payload['records_imported'] ?? 0);
        $failed = (int) ($payload['records_failed'] ?? 0);

        if (($payload['dry_run'] ?? false) && $failed === 0) {
            return 'dry-run';
        }

        if ($failed === 0) {
            return 'completed';
        }

        if ($imported === 0 && $failed > 0) {
            return 'failed';
        }

        return 'partial';
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
