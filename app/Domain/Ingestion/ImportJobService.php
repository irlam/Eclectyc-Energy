<?php
/**
 * eclectyc-energy/app/Domain/Ingestion/ImportJobService.php
 * Service for managing async import jobs
 * Last updated: 2025-11-07
 */

namespace App\Domain\Ingestion;

use PDO;
use Ramsey\Uuid\Uuid;
use Exception;

class ImportJobService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a new import job
     */
    public function createJob(
        string $filename,
        string $filePath,
        string $importType,
        ?int $userId = null,
        bool $dryRun = false,
        ?string $notes = null,
        string $priority = 'normal',
        ?array $tags = null,
        ?array $metadata = null,
        int $maxRetries = 3
    ): string {
        $batchId = Uuid::uuid4()->toString();
        
        $stmt = $this->pdo->prepare('
            INSERT INTO import_jobs (
                batch_id, user_id, filename, file_path, import_type, 
                dry_run, status, queued_at, notes, priority, tags, metadata, max_retries
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $batchId,
            $userId,
            $filename,
            $filePath,
            $importType,
            $dryRun ? 1 : 0,
            'queued',
            $notes,
            $priority,
            $tags ? json_encode($tags) : null,
            $metadata ? json_encode($metadata) : null,
            $maxRetries
        ]);
        
        return $batchId;
    }

    /**
     * Update job status
     */
    public function updateStatus(string $batchId, string $status, ?string $errorMessage = null): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE import_jobs 
            SET status = ?, 
                error_message = ?,
                last_error = CASE WHEN ? IS NOT NULL THEN ? ELSE last_error END,
                started_at = CASE WHEN ? = "processing" AND started_at IS NULL THEN NOW() ELSE started_at END,
                completed_at = CASE WHEN ? IN ("completed", "failed", "cancelled") THEN NOW() ELSE completed_at END
            WHERE batch_id = ?
        ');
        
        $stmt->execute([$status, $errorMessage, $errorMessage, $errorMessage, $status, $status, $batchId]);
    }

    /**
     * Update job progress
     */
    public function updateProgress(
        string $batchId,
        int $processedRows,
        int $importedRows,
        int $failedRows,
        ?int $totalRows = null
    ): void {
        $stmt = $this->pdo->prepare('
            UPDATE import_jobs 
            SET processed_rows = ?,
                imported_rows = ?,
                failed_rows = ?,
                total_rows = COALESCE(?, total_rows)
            WHERE batch_id = ?
        ');
        
        $stmt->execute([
            $processedRows,
            $importedRows,
            $failedRows,
            $totalRows,
            $batchId
        ]);
    }

    /**
     * Complete a job with final results
     */
    public function completeJob(string $batchId, array $summary, ?string $status = 'completed'): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE import_jobs 
            SET status = ?,
                processed_rows = ?,
                imported_rows = ?,
                failed_rows = ?,
                summary = ?,
                completed_at = NOW()
            WHERE batch_id = ?
        ');
        
        $stmt->execute([
            $status,
            $summary['records_processed'] ?? 0,
            $summary['records_imported'] ?? 0,
            $summary['records_failed'] ?? 0,
            json_encode($summary),
            $batchId
        ]);
    }

    /**
     * Get job status and progress
     */
    public function getJob(string $batchId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT ij.*, u.name as user_name, u.email as user_email
            FROM import_jobs ij
            LEFT JOIN users u ON ij.user_id = u.id
            WHERE ij.batch_id = ?
        ');
        
        $stmt->execute([$batchId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$job) {
            return null;
        }
        
        // Decode JSON summary
        if (!empty($job['summary'])) {
            $job['summary'] = json_decode($job['summary'], true);
        }
        
        // Calculate progress percentage
        $job['progress_percent'] = 0;
        if ($job['total_rows'] && $job['total_rows'] > 0) {
            $job['progress_percent'] = round(
                ($job['processed_rows'] / $job['total_rows']) * 100,
                2
            );
        }
        
        return $job;
    }

    /**
     * Get recent jobs
     */
    public function getRecentJobs(int $limit = 20, ?string $status = null): array
    {
        $sql = '
            SELECT ij.*, u.name as user_name, u.email as user_email
            FROM import_jobs ij
            LEFT JOIN users u ON ij.user_id = u.id
        ';
        
        if ($status) {
            $sql .= ' WHERE ij.status = ?';
        }
        
        $sql .= ' ORDER BY ij.queued_at DESC LIMIT ?';
        
        $stmt = $this->pdo->prepare($sql);
        
        if ($status) {
            $stmt->execute([$status, $limit]);
        } else {
            $stmt->execute([$limit]);
        }
        
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($jobs as &$job) {
            if (!empty($job['summary'])) {
                $job['summary'] = json_decode($job['summary'], true);
            }
            
            // Calculate progress percentage
            $job['progress_percent'] = 0;
            if ($job['total_rows'] && $job['total_rows'] > 0) {
                $job['progress_percent'] = round(
                    ($job['processed_rows'] / $job['total_rows']) * 100,
                    2
                );
            }
        }
        
        return $jobs;
    }

    /**
     * Get jobs that need processing (including retries)
     */
    public function getQueuedJobs(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM import_jobs
            WHERE status = "queued" 
               OR (status = "failed" AND retry_count < max_retries AND (retry_at IS NULL OR retry_at <= NOW()))
            ORDER BY priority DESC, queued_at ASC
            LIMIT ?
        ');
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark a job for retry
     */
    public function retryJob(string $batchId, int $delaySeconds = 0): bool
    {
        // First check if we can retry
        $stmt = $this->pdo->prepare('
            SELECT retry_count, max_retries FROM import_jobs WHERE batch_id = ?
        ');
        $stmt->execute([$batchId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$job || $job['retry_count'] >= $job['max_retries']) {
            return false;
        }
        
        $retryAt = $delaySeconds > 0 
            ? date('Y-m-d H:i:s', time() + $delaySeconds)
            : null;
        
        $stmt = $this->pdo->prepare('
            UPDATE import_jobs 
            SET status = "queued",
                retry_count = retry_count + 1,
                retry_at = ?,
                error_message = NULL,
                started_at = NULL,
                completed_at = NULL
            WHERE batch_id = ?
        ');
        
        $stmt->execute([$retryAt, $batchId]);
        return true;
    }

    /**
     * Check if job can be retried
     */
    public function canRetry(string $batchId): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT retry_count, max_retries FROM import_jobs WHERE batch_id = ?
        ');
        $stmt->execute([$batchId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $job && $job['retry_count'] < $job['max_retries'];
    }

    /**
     * Mark alert as sent for a job
     */
    public function markAlertSent(string $batchId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE import_jobs 
            SET alert_sent = TRUE, alert_sent_at = NOW()
            WHERE batch_id = ?
        ');
        $stmt->execute([$batchId]);
    }

    /**
     * Get failed jobs that need alerts
     */
    public function getFailedJobsNeedingAlerts(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM import_jobs
            WHERE status = "failed" 
              AND alert_sent = FALSE
              AND retry_count >= max_retries
            ORDER BY completed_at DESC
            LIMIT ?
        ');
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Clean up old completed jobs
     */
    public function cleanupOldJobs(int $daysOld = 30): int
    {
        $stmt = $this->pdo->prepare('
            DELETE FROM import_jobs
            WHERE status IN ("completed", "failed", "cancelled")
            AND completed_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ');
        
        $stmt->execute([$daysOld]);
        return $stmt->rowCount();
    }
}
