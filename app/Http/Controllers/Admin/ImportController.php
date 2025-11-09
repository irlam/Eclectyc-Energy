<?php
/**
 * eclectyc-energy/app/Http/Controllers/Admin/ImportController.php
 * Admin-facing CSV import workflow with optional dry-run support.
 */

namespace App\Http\Controllers\Admin;

use App\Domain\Ingestion\CsvIngestionService;
use App\Domain\Ingestion\ImportQueueStub;
use App\Domain\Ingestion\IngestionResult;
use App\Domain\Ingestion\ImportJobService;
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

        // Fetch sites and tariffs for optional selection
        $sites = [];
        $tariffs = [];
        if ($this->pdo) {
            try {
                $sitesStmt = $this->pdo->query('SELECT id, name FROM sites WHERE is_active = 1 ORDER BY name ASC');
                $sites = $sitesStmt->fetchAll() ?: [];
                
                $tariffsStmt = $this->pdo->query('SELECT id, name, supplier_id FROM tariffs WHERE is_active = 1 ORDER BY name ASC');
                $tariffs = $tariffsStmt->fetchAll() ?: [];
            } catch (\PDOException $e) {
                // Silently fail - optional features
            }
        }

        return $this->view->render($response, 'admin/imports.twig', [
            'page_title' => 'Data Imports',
            'flash' => $flash,
            'sites' => $sites,
            'tariffs' => $tariffs,
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

        $stmt = $this->pdo->prepare('
            SELECT a.id, a.user_id, a.new_values, a.status, a.retry_count, a.parent_batch_id, a.created_at,
                   u.name AS user_name, u.email AS user_email
              FROM audit_logs a
              LEFT JOIN users u ON a.user_id = u.id
             WHERE a.action = :action
             ORDER BY a.created_at DESC
             LIMIT :limit
        ');

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

            // Use database status if available, otherwise derive from payload
            $status = $row['status'] ?? $this->deriveStatus($payload);
            $importType = strtolower($payload['format'] ?? $payload['meta']['format'] ?? 'unknown');

            if ($statusFilter && $status !== $statusFilter) {
                continue;
            }

            if ($typeFilter && $importType !== $typeFilter) {
                continue;
            }

            $entries[] = [
                'id' => (int) $row['id'],
                'user_name' => $row['user_name'] ?? null,
                'user_email' => $row['user_email'] ?? null,
                'created_at' => $row['created_at'],
                'completed_at' => $row['created_at'],
                'batch_id' => $payload['batch_id'] ?? null,
                'import_type' => $importType,
                'status' => $status,
                'retry_count' => (int) ($row['retry_count'] ?? 0),
                'parent_batch_id' => $row['parent_batch_id'] ?? null,
                'is_retry' => (bool) ($payload['is_retry'] ?? false),
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
        // Check for explicit status from audit log
        if (isset($payload['status'])) {
            return $payload['status'];
        }
        
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
        $async = isset($data['async']); // New: support async processing
        $defaultSiteId = !empty($data['default_site_id']) ? (int) $data['default_site_id'] : null;
        $defaultTariffId = !empty($data['default_tariff_id']) ? (int) $data['default_tariff_id'] : null;
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

        // Handle async import
        if ($async) {
            return $this->uploadAsync($uploadedFile, $format, $dryRun, $response, $defaultSiteId, $defaultTariffId);
        }

        // Original synchronous import
        $tempPath = tempnam(sys_get_temp_dir(), 'import_');
        $uploadedFile->moveTo($tempPath);

        $service = new CsvIngestionService($this->pdo);
        $batchId = Uuid::uuid4()->toString();
        $summary = null;

        // Progress callback for UI feedback
        $progressCallback = function (int $processed, int $imported, int $warnings) use ($batchId) {
            // Log progress for UI feedback
            error_log(sprintf(
                '[Import Progress] Batch: %s | Processed: %d | Imported: %d | Warnings: %d',
                substr($batchId, 0, 8),
                $processed,
                $imported,
                $warnings
            ));
        };

        try {
            /** @var IngestionResult $result */
            $result = $service->ingestFromCsv($tempPath, $format, $batchId, $dryRun, $this->currentUserId(), $progressCallback);
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

    /**
     * Handle async upload - queue the job for background processing
     */
    private function uploadAsync($uploadedFile, string $format, bool $dryRun, Response $response, ?int $defaultSiteId = null, ?int $defaultTariffId = null): Response
    {
        try {
            $jobService = new ImportJobService($this->pdo);
            
            // Create a permanent storage directory for import files
            $storageDir = dirname(__DIR__, 3) . '/storage/imports';
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0755, true);
            }
            
            // Save the file with a unique name
            $filename = $uploadedFile->getClientFilename();
            $uniqueFilename = time() . '_' . preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);
            $filePath = $storageDir . '/' . $uniqueFilename;
            $uploadedFile->moveTo($filePath);
            
            // Create the import job with optional site and tariff
            $batchId = $jobService->createJob(
                $filename,
                $filePath,
                $format,
                $this->currentUserId(),
                $dryRun,
                $defaultSiteId,
                $defaultTariffId
            );
            
            $this->setFlash('success', 'Import job queued successfully. You can close this page and check the status later.', [
                'batch_id' => $batchId,
                'filename' => $filename,
                'format' => $format,
                'async' => true,
            ]);
            
            return $this->redirect($response, '/admin/imports/status/' . $batchId);
            
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Failed to queue import job: ' . $e->getMessage());
            return $this->redirect($response, '/admin/imports');
        }
    }

    /**
     * Retry a failed import batch
     * 
     * Note: This is a stub implementation. Full retry functionality requires:
     * 1. Storing uploaded files persistently (e.g., in storage/imports/)
     * 2. Tracking file paths in the database
     * 3. Implementing background queue processing
     */
    public function retry(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/imports/history');
        }

        $data = $request->getParsedBody() ?? [];
        $batchId = $data['batch_id'] ?? null;

        if (!$batchId) {
            $this->setFlash('error', 'Batch ID is required for retry.');
            return $this->redirect($response, '/admin/imports/history');
        }

        // Validate batch ID format
        if (!preg_match('/^[a-f0-9\-]{36}$/i', $batchId)) {
            $this->setFlash('error', 'Invalid batch ID format.');
            return $this->redirect($response, '/admin/imports/history');
        }

        // Fetch the original batch info
        $stmt = $this->pdo->prepare('
            SELECT id, new_values 
            FROM audit_logs 
            WHERE action = :action 
            AND JSON_EXTRACT(new_values, "$.batch_id") = :batch_id
            ORDER BY created_at DESC 
            LIMIT 1
        ');
        $stmt->execute([
            'action' => 'import_csv',
            'batch_id' => $batchId,
        ]);
        
        $originalBatch = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$originalBatch) {
            $this->setFlash('error', 'Original batch not found.');
            return $this->redirect($response, '/admin/imports/history');
        }

        // Queue the retry (stub implementation)
        $queue = new ImportQueueStub();
        $payload = json_decode($originalBatch['new_values'], true);
        $format = $payload['format'] ?? 'hh';
        
        // TODO: In production, implement proper file storage and retrieval:
        // 1. Store uploaded files with batch_id in filename
        // 2. Retrieve file path from storage
        // 3. Pass actual file path to queue
        // For now, just mark the intent to retry
        $this->setFlash('warning', 'Retry queued. Note: Full retry functionality requires file storage implementation. Original file must be re-uploaded for processing.');
        
        return $this->redirect($response, '/admin/imports/history');
    }

    /**
     * View the status of an import job
     */
    public function status(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            return $this->view->render($response, 'admin/import_status.twig', [
                'page_title' => 'Import Status',
                'error' => 'Database connection unavailable.',
            ]);
        }

        $batchId = $args['batchId'] ?? null;
        
        if (!$batchId) {
            $this->setFlash('error', 'Batch ID is required.');
            return $this->redirect($response, '/admin/imports');
        }

        $jobService = new ImportJobService($this->pdo);
        $job = $jobService->getJob($batchId);

        if (!$job) {
            $this->setFlash('error', 'Import job not found.');
            return $this->redirect($response, '/admin/imports');
        }

        return $this->view->render($response, 'admin/import_status.twig', [
            'page_title' => 'Import Status',
            'job' => $job,
        ]);
    }

    /**
     * List all active/recent import jobs
     */
    public function jobs(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            return $this->view->render($response, 'admin/import_jobs.twig', [
                'page_title' => 'Import Jobs',
                'error' => 'Database connection unavailable.',
                'jobs' => [],
            ]);
        }

        $query = $request->getQueryParams();
        $status = $query['status'] ?? null;
        $limit = isset($query['limit']) ? max(10, min(100, (int) $query['limit'])) : 50;

        $jobService = new ImportJobService($this->pdo);
        $jobs = $jobService->getRecentJobs($limit, $status);

        return $this->view->render($response, 'admin/import_jobs.twig', [
            'page_title' => 'Import Jobs',
            'jobs' => $jobs,
            'filters' => [
                'status' => $status,
                'limit' => $limit,
            ],
        ]);
    }

    /**
     * Delete an import job and all associated data
     */
    public function deleteJob(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/imports/jobs');
        }

        $jobId = isset($args['id']) ? (int) $args['id'] : null;
        
        if (!$jobId) {
            $this->setFlash('error', 'Job ID is required.');
            return $this->redirect($response, '/admin/imports/jobs');
        }

        try {
            $this->pdo->beginTransaction();

            // Get job details including batch_id
            $stmt = $this->pdo->prepare('
                SELECT batch_id, file_path, import_type, status
                FROM import_jobs
                WHERE id = :id
            ');
            $stmt->execute(['id' => $jobId]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                $this->pdo->rollBack();
                $this->setFlash('error', 'Import job not found.');
                return $this->redirect($response, '/admin/imports/jobs');
            }

            // Don't allow deletion of jobs that are currently processing
            if ($job['status'] === 'processing') {
                $this->pdo->rollBack();
                $this->setFlash('error', 'Cannot delete a job that is currently processing. Please wait for it to complete or fail.');
                return $this->redirect($response, '/admin/imports/jobs');
            }

            $batchId = $job['batch_id'];
            $deletedReadings = 0;
            $deletedMeters = 0;

            // Delete meter readings associated with this batch
            // Note: meter_readings table may have a batch_id column to track which import created them
            // If not, we may need to rely on audit logs or other tracking mechanisms
            $checkBatchColumn = $this->pdo->query("
                SELECT COUNT(*) as count 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'meter_readings' 
                AND COLUMN_NAME = 'batch_id'
            ")->fetch(PDO::FETCH_ASSOC);

            if ($checkBatchColumn['count'] > 0) {
                $deleteReadingsStmt = $this->pdo->prepare('
                    DELETE FROM meter_readings WHERE batch_id = :batch_id
                ');
                $deleteReadingsStmt->execute(['batch_id' => $batchId]);
                $deletedReadings = $deleteReadingsStmt->rowCount();
            }

            // Check if meters were created by this import (auto-created meters)
            // Similar approach - check if meters table has batch_id column
            $checkMetersBatchColumn = $this->pdo->query("
                SELECT COUNT(*) as count 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'meters' 
                AND COLUMN_NAME = 'batch_id'
            ")->fetch(PDO::FETCH_ASSOC);

            if ($checkMetersBatchColumn['count'] > 0) {
                // Only delete meters that have no other readings
                $deleteMetersStmt = $this->pdo->prepare('
                    DELETE m FROM meters m
                    WHERE m.batch_id = :batch_id
                    AND NOT EXISTS (
                        SELECT 1 FROM meter_readings mr 
                        WHERE mr.meter_id = m.id 
                        AND (mr.batch_id != :batch_id2 OR mr.batch_id IS NULL)
                    )
                ');
                $deleteMetersStmt->execute([
                    'batch_id' => $batchId,
                    'batch_id2' => $batchId
                ]);
                $deletedMeters = $deleteMetersStmt->rowCount();
            }

            // Delete audit log entries for this import
            // Use JSON_UNQUOTE to properly extract the batch_id value
            $deleteAuditStmt = $this->pdo->prepare('
                DELETE FROM audit_logs 
                WHERE action = :action 
                AND JSON_UNQUOTE(JSON_EXTRACT(new_values, "$.batch_id")) = :batch_id
            ');
            $deleteAuditStmt->execute([
                'action' => 'import_csv',
                'batch_id' => $batchId,
            ]);
            $deletedAuditLogs = $deleteAuditStmt->rowCount();

            // Delete the import job itself
            $deleteJobStmt = $this->pdo->prepare('DELETE FROM import_jobs WHERE id = :id');
            $deleteJobStmt->execute(['id' => $jobId]);

            // Delete the uploaded file if it exists
            if (!empty($job['file_path']) && file_exists($job['file_path'])) {
                @unlink($job['file_path']);
            }

            $this->pdo->commit();

            $message = "Import job deleted successfully.";
            $details = [];
            
            if ($deletedReadings > 0) {
                $details[] = "{$deletedReadings} reading(s)";
            }
            if ($deletedMeters > 0) {
                $details[] = "{$deletedMeters} meter(s)";
            }
            if ($deletedAuditLogs > 0) {
                $details[] = "{$deletedAuditLogs} audit log(s)";
            }
            
            if (!empty($details)) {
                $message .= " Removed: " . implode(', ', $details) . ".";
            }

            $this->setFlash('success', $message);

        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            error_log('Failed to delete import job: ' . $e->getMessage());
            $this->setFlash('error', 'Failed to delete import job: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log('Failed to delete import job: ' . $e->getMessage());
            $this->setFlash('error', 'An unexpected error occurred while deleting the import job.');
        }

        return $this->redirect($response, '/admin/imports/jobs');
    }

    /**
     * Cancel a running or queued import job
     */
    public function cancelJob(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/imports/jobs');
        }

        $jobId = isset($args['id']) ? (int) $args['id'] : null;
        
        if (!$jobId) {
            $this->setFlash('error', 'Job ID is required.');
            return $this->redirect($response, '/admin/imports/jobs');
        }

        try {
            // Get the batch_id for this job
            $stmt = $this->pdo->prepare('SELECT batch_id FROM import_jobs WHERE id = :id');
            $stmt->execute(['id' => $jobId]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                $this->setFlash('error', 'Import job not found.');
                return $this->redirect($response, '/admin/imports/jobs');
            }

            $jobService = new ImportJobService($this->pdo);
            $success = $jobService->cancelJob($job['batch_id']);

            if ($success) {
                $this->setFlash('success', 'Import job cancelled successfully.');
            } else {
                $this->setFlash('error', 'Could not cancel job. Only queued or processing jobs can be cancelled.');
            }

        } catch (\Throwable $e) {
            error_log('Failed to cancel import job: ' . $e->getMessage());
            $this->setFlash('error', 'Failed to cancel import job: ' . $e->getMessage());
        }

        return $this->redirect($response, '/admin/imports/jobs');
    }

    /**
     * Delete a single import history entry
     */
    public function deleteHistory(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Database connection unavailable.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $entryId = isset($args['id']) ? (int) $args['id'] : null;
        
        if (!$entryId) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Entry ID is required.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $stmt = $this->pdo->prepare('DELETE FROM audit_logs WHERE id = :id AND action = :action');
            $stmt->execute([
                'id' => $entryId,
                'action' => 'import_csv',
            ]);

            if ($stmt->rowCount() > 0) {
                $response->getBody()->write(json_encode(['success' => true, 'deleted' => 1]));
                return $response->withHeader('Content-Type', 'application/json');
            } else {
                $response->getBody()->write(json_encode(['success' => false, 'message' => 'Entry not found.']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
            }
        } catch (\PDOException $e) {
            error_log('Failed to delete import history: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Delete multiple import history entries
     */
    public function deleteHistoryBulk(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Database connection unavailable.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        $data = json_decode($request->getBody()->getContents(), true);
        $ids = $data['ids'] ?? [];

        if (empty($ids) || !is_array($ids)) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'No IDs provided.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->pdo->prepare("DELETE FROM audit_logs WHERE id IN ($placeholders) AND action = ?");
            
            $params = array_map('intval', $ids);
            $params[] = 'import_csv';
            
            $stmt->execute($params);
            $deleted = $stmt->rowCount();

            $response->getBody()->write(json_encode(['success' => true, 'deleted' => $deleted]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            error_log('Failed to delete import history: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    /**
     * Delete all import history
     */
    public function deleteHistoryAll(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Database connection unavailable.']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }

        try {
            $stmt = $this->pdo->prepare('DELETE FROM audit_logs WHERE action = :action');
            $stmt->execute(['action' => 'import_csv']);
            $deleted = $stmt->rowCount();

            $response->getBody()->write(json_encode(['success' => true, 'deleted' => $deleted]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            error_log('Failed to delete import history: ' . $e->getMessage());
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
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
