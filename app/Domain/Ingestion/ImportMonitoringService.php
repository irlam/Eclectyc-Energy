<?php
/**
 * eclectyc-energy/app/Domain/Ingestion/ImportMonitoringService.php
 * Service for monitoring import job health and performance
 * Last updated: 2025-11-07
 */

namespace App\Domain\Ingestion;

use PDO;

class ImportMonitoringService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get overall import system health
     */
    public function getSystemHealth(): array
    {
        $health = [
            'status' => 'healthy',
            'metrics' => [],
            'alerts' => [],
        ];

        // Check for stuck jobs
        $stuckJobs = $this->getStuckJobs();
        if (count($stuckJobs) > 0) {
            $health['status'] = 'degraded';
            $health['alerts'][] = [
                'type' => 'stuck_jobs',
                'severity' => 'warning',
                'message' => count($stuckJobs) . ' job(s) stuck in processing state',
                'count' => count($stuckJobs),
            ];
        }

        // Check for high failure rate
        $failureRate = $this->getRecentFailureRate(24);
        if ($failureRate > 50) {
            $health['status'] = 'critical';
            $health['alerts'][] = [
                'type' => 'high_failure_rate',
                'severity' => 'critical',
                'message' => 'High failure rate: ' . round($failureRate, 1) . '% in last 24h',
                'rate' => $failureRate,
            ];
        } elseif ($failureRate > 25) {
            $health['status'] = $health['status'] === 'critical' ? 'critical' : 'degraded';
            $health['alerts'][] = [
                'type' => 'elevated_failure_rate',
                'severity' => 'warning',
                'message' => 'Elevated failure rate: ' . round($failureRate, 1) . '% in last 24h',
                'rate' => $failureRate,
            ];
        }

        // Check queue depth
        $queueDepth = $this->getQueueDepth();
        if ($queueDepth > 100) {
            $health['status'] = $health['status'] === 'critical' ? 'critical' : 'degraded';
            $health['alerts'][] = [
                'type' => 'queue_backlog',
                'severity' => 'warning',
                'message' => 'Queue backlog: ' . $queueDepth . ' jobs waiting',
                'count' => $queueDepth,
            ];
        }

        // Get performance metrics
        $health['metrics'] = $this->getPerformanceMetrics();

        return $health;
    }

    /**
     * Get jobs stuck in processing state
     */
    public function getStuckJobs(int $thresholdMinutes = 60): array
    {
        $stmt = $this->pdo->prepare('
            SELECT batch_id, filename, started_at, 
                   TIMESTAMPDIFF(MINUTE, started_at, NOW()) as minutes_stuck
            FROM import_jobs
            WHERE status = "processing"
              AND started_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ORDER BY started_at ASC
        ');
        
        $stmt->execute([$thresholdMinutes]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get failure rate for recent period
     */
    public function getRecentFailureRate(int $hours = 24): float
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = "failed" AND retry_count >= max_retries THEN 1 ELSE 0 END) as failed
            FROM import_jobs
            WHERE queued_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
              AND status IN ("completed", "failed")
        ');
        
        $stmt->execute([$hours]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['total'] == 0) {
            return 0;
        }
        
        return ($result['failed'] / $result['total']) * 100;
    }

    /**
     * Get queue depth
     */
    public function getQueueDepth(): int
    {
        $stmt = $this->pdo->query('
            SELECT COUNT(*) as count
            FROM import_jobs
            WHERE status = "queued" OR (status = "failed" AND retry_count < max_retries)
        ');
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) $result['count'];
    }

    /**
     * Get performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        // Get metrics for completed jobs in last 24 hours
        $stmt = $this->pdo->query('
            SELECT 
                COUNT(*) as jobs_completed,
                AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_duration_seconds,
                MAX(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as max_duration_seconds,
                MIN(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as min_duration_seconds,
                SUM(imported_rows) as total_rows_imported,
                AVG(imported_rows) as avg_rows_per_job
            FROM import_jobs
            WHERE status = "completed"
              AND completed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ');
        
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Add current queue metrics
        $stmt = $this->pdo->query('
            SELECT 
                COUNT(*) as queued_count,
                SUM(CASE WHEN status = "processing" THEN 1 ELSE 0 END) as processing_count
            FROM import_jobs
            WHERE status IN ("queued", "processing")
        ');
        
        $queueMetrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return array_merge($metrics, $queueMetrics);
    }

    /**
     * Get retry statistics
     */
    public function getRetryStatistics(int $hours = 24): array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                COUNT(*) as total_with_retries,
                AVG(retry_count) as avg_retries,
                MAX(retry_count) as max_retries,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as eventually_succeeded,
                SUM(CASE WHEN status = "failed" AND retry_count >= max_retries THEN 1 ELSE 0 END) as permanently_failed
            FROM import_jobs
            WHERE retry_count > 0
              AND queued_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        ');
        
        $stmt->execute([$hours]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mark stuck jobs for manual intervention
     */
    public function handleStuckJobs(int $thresholdMinutes = 60): int
    {
        $stmt = $this->pdo->prepare('
            UPDATE import_jobs
            SET status = "failed",
                error_message = CONCAT("Job stuck in processing state for over ", ?, " minutes - marked as failed"),
                completed_at = NOW()
            WHERE status = "processing"
              AND started_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ');
        
        $stmt->execute([$thresholdMinutes, $thresholdMinutes]);
        return $stmt->rowCount();
    }
}
