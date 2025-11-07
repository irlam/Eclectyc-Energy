<?php
/**
 * eclectyc-energy/app/Services/CarbonIntensityService.php
 * Fetches real-time carbon intensity data from National Grid ESO API
 */

namespace App\Services;

use App\Domain\External\ExternalDataService;
use DateTimeImmutable;
use Exception;

class CarbonIntensityService
{
    private string $apiUrl;
    private ?string $apiKey;
    private ExternalDataService $externalDataService;

    public function __construct(ExternalDataService $externalDataService)
    {
        $this->apiUrl = $_ENV['CARBON_API_URL'] ?? 'https://api.carbonintensity.org.uk';
        $this->apiKey = $_ENV['CARBON_API_KEY'] ?? null;
        $this->externalDataService = $externalDataService;
    }

    /**
     * Fetch current carbon intensity for GB
     */
    public function getCurrentIntensity(): ?array
    {
        try {
            $url = $this->apiUrl . '/intensity';
            $data = $this->makeApiRequest($url);
            
            if (!$data || !isset($data['data'][0])) {
                return null;
            }

            $intensity = $data['data'][0];
            return [
                'from' => $intensity['from'],
                'to' => $intensity['to'],
                'forecast' => $intensity['intensity']['forecast'] ?? null,
                'actual' => $intensity['intensity']['actual'] ?? null,
                'index' => $intensity['intensity']['index'] ?? null,
            ];
        } catch (Exception $e) {
            error_log("Carbon Intensity API Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch carbon intensity for a specific date range
     */
    public function getIntensityForDateRange(DateTimeImmutable $from, DateTimeImmutable $to): ?array
    {
        try {
            $fromStr = $from->format('Y-m-d\TH:i\Z');
            $toStr = $to->format('Y-m-d\TH:i\Z');
            
            $url = $this->apiUrl . "/intensity/{$fromStr}/{$toStr}";
            $data = $this->makeApiRequest($url);
            
            if (!$data || !isset($data['data'])) {
                return null;
            }

            return $data['data'];
        } catch (Exception $e) {
            error_log("Carbon Intensity API Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch today's carbon intensity forecast
     */
    public function getTodaysForecast(): ?array
    {
        try {
            $url = $this->apiUrl . '/intensity/date';
            $data = $this->makeApiRequest($url);
            
            if (!$data || !isset($data['data'])) {
                return null;
            }

            return $data['data'];
        } catch (Exception $e) {
            error_log("Carbon Intensity API Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch and store current carbon intensity in database
     */
    public function fetchAndStoreCurrentIntensity(): bool
    {
        $current = $this->getCurrentIntensity();
        if (!$current) {
            return false;
        }

        try {
            $datetime = new DateTimeImmutable($current['from']);
            
            $this->externalDataService->storeCarbonIntensity('GB', $datetime, [
                'intensity' => $current['forecast'] ?? $current['actual'],
                'forecast' => $current['forecast'],
                'actual' => $current['actual'],
                'source' => 'national-grid-eso-api'
            ]);

            return true;
        } catch (Exception $e) {
            error_log("Error storing carbon intensity: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch and store today's forecast data
     */
    public function fetchAndStoreTodaysForecast(): int
    {
        $forecast = $this->getTodaysForecast();
        if (!$forecast) {
            return 0;
        }

        $stored = 0;
        foreach ($forecast as $period) {
            try {
                $datetime = new DateTimeImmutable($period['from']);
                
                $this->externalDataService->storeCarbonIntensity('GB', $datetime, [
                    'intensity' => $period['intensity']['forecast'] ?? $period['intensity']['actual'],
                    'forecast' => $period['intensity']['forecast'] ?? null,
                    'actual' => $period['intensity']['actual'] ?? null,
                    'source' => 'national-grid-eso-api'
                ]);
                
                $stored++;
            } catch (Exception $e) {
                error_log("Error storing forecast period: " . $e->getMessage());
            }
        }

        return $stored;
    }

    /**
     * Get carbon intensity classification
     */
    public function getIntensityClassification(float $intensity): array
    {
        if ($intensity <= 150) {
            return ['level' => 'very-low', 'label' => 'Very Low', 'color' => '#00FF00'];
        } elseif ($intensity <= 200) {
            return ['level' => 'low', 'label' => 'Low', 'color' => '#90EE90'];
        } elseif ($intensity <= 250) {
            return ['level' => 'moderate', 'label' => 'Moderate', 'color' => '#FFD700'];
        } elseif ($intensity <= 300) {
            return ['level' => 'high', 'label' => 'High', 'color' => '#FFA500'];
        } else {
            return ['level' => 'very-high', 'label' => 'Very High', 'color' => '#FF0000'];
        }
    }

    /**
     * Get dashboard summary for carbon intensity
     */
    public function getDashboardSummary(): array
    {
        // Get current intensity
        $current = $this->getCurrentIntensity();
        
        // Get recent stored data for trend
        $recent = $this->externalDataService->getCarbonIntensity(
            'GB',
            new DateTimeImmutable('-24 hours'),
            new DateTimeImmutable()
        );

        $currentIntensity = null;
        $trend = 'stable';
        $classification = null;

        if ($current) {
            $currentIntensity = $current['forecast'] ?? $current['actual'];
            if ($currentIntensity) {
                $classification = $this->getIntensityClassification($currentIntensity);
            }
        }

        // Calculate trend from recent data
        if (count($recent) >= 2) {
            $lastTwo = array_slice($recent, -2);
            $diff = $lastTwo[1]['intensity'] - $lastTwo[0]['intensity'];
            
            if ($diff > 10) {
                $trend = 'rising';
            } elseif ($diff < -10) {
                $trend = 'falling';
            }
        }

        return [
            'current_intensity' => $currentIntensity,
            'classification' => $classification,
            'trend' => $trend,
            'last_updated' => $current ? $current['from'] : null,
            'recent_data' => array_slice($recent, -12), // Last 12 periods
        ];
    }

    /**
     * Make HTTP request to the API
     */
    private function makeApiRequest(string $url): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Accept: application/json',
                    'User-Agent: Eclectyc-Energy/1.0'
                ] + ($this->apiKey ? ["Authorization: Bearer {$this->apiKey}"] : []),
                'timeout' => 10,
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception("Failed to fetch data from: {$url}");
        }

        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response from API");
        }

        return $data;
    }
}