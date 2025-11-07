<?php
/**
 * eclectyc-energy/app/Domain/Orchestration/TelemetryService.php
 * Records execution metrics and telemetry for scheduled tasks.
 */

namespace App\Domain\Orchestration;

use PDO;
use DateTimeImmutable;
use Throwable;

class TelemetryService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Record the start of an execution.
     */
    public function recordStart(string $executionId, string $range, DateTimeImmutable $targetDate): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO scheduler_executions 
                (execution_id, range_type, target_date, status, started_at)
            VALUES (:execution_id, :range_type, :target_date, :status, NOW())
        ');
        
        $stmt->execute([
            'execution_id' => $executionId,
            'range_type' => $range,
            'target_date' => $targetDate->format('Y-m-d'),
            'status' => 'running',
        ]);
    }

    /**
     * Record successful completion with metrics.
     */
    public function recordSuccess(string $executionId, float $duration, array $result): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE scheduler_executions
            SET status = :status,
                completed_at = NOW(),
                duration_seconds = :duration,
                meters_processed = :meters_processed,
                error_count = :error_count,
                warning_count = :warning_count,
                telemetry_data = :telemetry_data
            WHERE execution_id = :execution_id
        ');
        
        $stmt->execute([
            'execution_id' => $executionId,
            'status' => 'completed',
            'duration' => $duration,
            'meters_processed' => $result['meters_processed'] ?? 0,
            'error_count' => $result['errors'] ?? 0,
            'warning_count' => $result['warnings'] ?? 0,
            'telemetry_data' => json_encode($result),
        ]);
    }

    /**
     * Record execution failure.
     */
    public function recordFailure(string $executionId, float $duration, Throwable $exception): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE scheduler_executions
            SET status = :status,
                completed_at = NOW(),
                duration_seconds = :duration,
                error_count = 1,
                error_message = :error_message,
                telemetry_data = :telemetry_data
            WHERE execution_id = :execution_id
        ');
        
        $stmt->execute([
            'execution_id' => $executionId,
            'status' => 'failed',
            'duration' => $duration,
            'error_message' => $exception->getMessage(),
            'telemetry_data' => json_encode([
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]),
        ]);
    }

    /**
     * Get recent execution history.
     */
    public function getRecentExecutions(int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM scheduler_executions
            ORDER BY started_at DESC
            LIMIT :limit
        ');
        
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Get execution statistics for a time period.
     */
    public function getStatistics(DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                range_type,
                COUNT(*) as total_executions,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed,
                AVG(duration_seconds) as avg_duration,
                SUM(meters_processed) as total_meters_processed,
                SUM(error_count) as total_errors,
                SUM(warning_count) as total_warnings
            FROM scheduler_executions
            WHERE started_at BETWEEN :start_date AND :end_date
            GROUP BY range_type
        ');
        
        $stmt->execute([
            'start_date' => $startDate->format('Y-m-d H:i:s'),
            'end_date' => $endDate->format('Y-m-d H:i:s'),
        ]);
        
        return $stmt->fetchAll() ?: [];
    }
}
