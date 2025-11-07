<?php
/**
 * eclectyc-energy/app/Domain/Orchestration/SchedulerOrchestrator.php
 * Orchestrates scheduled aggregation tasks with telemetry and failure alerts.
 */

namespace App\Domain\Orchestration;

use PDO;
use DateTimeImmutable;
use Exception;
use Throwable;

class SchedulerOrchestrator
{
    private PDO $pdo;
    private TelemetryService $telemetry;
    private AlertService $alertService;

    public function __construct(PDO $pdo, TelemetryService $telemetry, AlertService $alertService)
    {
        $this->pdo = $pdo;
        $this->telemetry = $telemetry;
        $this->alertService = $alertService;
    }

    /**
     * Execute scheduled aggregation with monitoring and alerts.
     * 
     * @param string $range Aggregation range (daily, weekly, monthly, annual)
     * @param DateTimeImmutable $targetDate Target date for aggregation
     * @return OrchestrationResult Execution result with telemetry
     */
    public function executeAggregation(string $range, DateTimeImmutable $targetDate): OrchestrationResult
    {
        $executionId = $this->generateExecutionId();
        $startTime = microtime(true);
        
        $this->telemetry->recordStart($executionId, $range, $targetDate);
        
        try {
            // Execute the aggregation
            $result = $this->runAggregation($range, $targetDate);
            
            $duration = microtime(true) - $startTime;
            $this->telemetry->recordSuccess($executionId, $duration, $result);
            
            // Check for warnings
            if ($result['warnings'] > 0) {
                $this->alertService->sendWarning($range, $result);
            }
            
            return new OrchestrationResult(
                success: true,
                executionId: $executionId,
                range: $range,
                duration: $duration,
                metricsProcessed: $result['meters_processed'],
                errors: $result['errors'],
                warnings: $result['warnings']
            );
            
        } catch (Throwable $e) {
            $duration = microtime(true) - $startTime;
            $this->telemetry->recordFailure($executionId, $duration, $e);
            $this->alertService->sendFailureAlert($range, $e);
            
            return new OrchestrationResult(
                success: false,
                executionId: $executionId,
                range: $range,
                duration: $duration,
                metricsProcessed: 0,
                errors: 1,
                warnings: 0,
                errorMessage: $e->getMessage()
            );
        }
    }

    /**
     * Execute all aggregation ranges for a given date.
     * 
     * @param DateTimeImmutable $targetDate Target date
     * @return array<OrchestrationResult> Results for each range
     */
    public function executeAllRanges(DateTimeImmutable $targetDate): array
    {
        $ranges = ['daily', 'weekly', 'monthly', 'annual'];
        $results = [];
        
        foreach ($ranges as $range) {
            $results[$range] = $this->executeAggregation($range, $targetDate);
        }
        
        // Send summary alert if any failures
        $failures = array_filter($results, fn($r) => !$r->isSuccess());
        if (!empty($failures)) {
            $this->alertService->sendSummaryAlert($results);
        }
        
        return $results;
    }

    private function runAggregation(string $range, DateTimeImmutable $targetDate): array
    {
        // This would call the actual aggregation logic
        // For now, return a mock result structure
        return [
            'meters_processed' => 0,
            'errors' => 0,
            'warnings' => 0,
        ];
    }

    private function generateExecutionId(): string
    {
        return uniqid('exec_', true);
    }
}
