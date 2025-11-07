<?php
/**
 * eclectyc-energy/app/Http/Controllers/Api/CarbonIntensityController.php
 * API controller for carbon intensity data
 */

namespace App\Http\Controllers\Api;

use App\Domain\External\ExternalDataService;
use App\Services\CarbonIntensityService;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CarbonIntensityController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get current carbon intensity data
     */
    public function getCurrent(Request $request, Response $response): Response
    {
        try {
            $externalDataService = new ExternalDataService($this->pdo);
            $carbonService = new CarbonIntensityService($externalDataService);
            
            $summary = $carbonService->getDashboardSummary();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $summary
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
                
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    /**
     * Manually refresh carbon intensity data from API
     */
    public function refresh(Request $request, Response $response): Response
    {
        try {
            $externalDataService = new ExternalDataService($this->pdo);
            $carbonService = new CarbonIntensityService($externalDataService);
            
            // Fetch current data
            $currentSuccess = $carbonService->fetchAndStoreCurrentIntensity();
            
            // Fetch today's forecast
            $forecastStored = $carbonService->fetchAndStoreTodaysForecast();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'current_updated' => $currentSuccess,
                    'forecast_periods_stored' => $forecastStored,
                    'timestamp' => (new DateTimeImmutable())->format('Y-m-d H:i:s')
                ]
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
                
        } catch (\Exception $e) {
            error_log("Carbon intensity refresh error: " . $e->getMessage());
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    /**
     * Get historical carbon intensity data
     */
    public function getHistory(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $days = (int) ($queryParams['days'] ?? 7);
            $region = $queryParams['region'] ?? 'GB';
            
            $externalDataService = new ExternalDataService($this->pdo);
            
            $endDate = new DateTimeImmutable();
            $startDate = $endDate->modify("-{$days} days");
            
            $data = $externalDataService->getCarbonIntensity($region, $startDate, $endDate);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => [
                    'region' => $region,
                    'start_date' => $startDate->format('Y-m-d H:i:s'),
                    'end_date' => $endDate->format('Y-m-d H:i:s'),
                    'records' => $data,
                    'count' => count($data)
                ]
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
                
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }
}