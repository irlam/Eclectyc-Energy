<?php
/**
 * eclectyc-energy/app/Http/Controllers/Api/ImportJobController.php
 * API endpoint for import job status and progress
 * Last updated: 2025-11-07
 */

namespace App\Http\Controllers\Api;

use App\Domain\Ingestion\ImportJobService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ImportJobController
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get status of a specific import job
     */
    public function getStatus(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            return $this->jsonResponse($response, [
                'error' => 'Database connection unavailable'
            ], 500);
        }

        $batchId = $args['batchId'] ?? null;
        
        if (!$batchId) {
            return $this->jsonResponse($response, [
                'error' => 'Batch ID is required'
            ], 400);
        }

        $jobService = new ImportJobService($this->pdo);
        $job = $jobService->getJob($batchId);

        if (!$job) {
            return $this->jsonResponse($response, [
                'error' => 'Import job not found'
            ], 404);
        }

        return $this->jsonResponse($response, $job);
    }

    /**
     * Get list of recent import jobs
     */
    public function getJobs(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            return $this->jsonResponse($response, [
                'error' => 'Database connection unavailable'
            ], 500);
        }

        $params = $request->getQueryParams();
        $status = $params['status'] ?? null;
        $limit = isset($params['limit']) ? min(100, max(1, (int) $params['limit'])) : 20;

        $jobService = new ImportJobService($this->pdo);
        $jobs = $jobService->getRecentJobs($limit, $status);

        return $this->jsonResponse($response, [
            'jobs' => $jobs,
            'count' => count($jobs),
        ]);
    }

    /**
     * Helper to create JSON response
     */
    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $payload = json_encode($data, JSON_PRETTY_PRINT);
        $response->getBody()->write($payload);
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
