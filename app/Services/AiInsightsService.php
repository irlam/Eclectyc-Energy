<?php

namespace App\Services;

use PDO;
use Exception;
use RuntimeException;

/**
 * AI Insights Service
 * 
 * Generates intelligent insights about energy consumption, costs, and optimization
 * opportunities using AI providers (OpenAI, Anthropic, Google AI, Azure OpenAI).
 */
class AiInsightsService
{
    private PDO $db;
    private array $config;
    
    // Supported AI providers
    const PROVIDER_OPENAI = 'openai';
    const PROVIDER_ANTHROPIC = 'anthropic';
    const PROVIDER_GOOGLE = 'google';
    const PROVIDER_AZURE = 'azure';
    
    // Insight types
    const TYPE_CONSUMPTION_PATTERN = 'consumption_pattern';
    const TYPE_COST_OPTIMIZATION = 'cost_optimization';
    const TYPE_ANOMALY_DETECTION = 'anomaly_detection';
    const TYPE_PREDICTIVE_MAINTENANCE = 'predictive_maintenance';
    const TYPE_CARBON_REDUCTION = 'carbon_reduction';
    
    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Generate insights for a specific meter
     */
    public function generateInsightsForMeter(int $meterId, ?string $insightType = null): array
    {
        $provider = $this->getConfiguredProvider();
        if (!$provider) {
            throw new RuntimeException('No AI provider configured. Please configure an API key in Settings.');
        }
        
        // Get meter data
        $meterData = $this->getMeterData($meterId);
        if (!$meterData) {
            throw new RuntimeException('Meter not found or no data available');
        }
        
        // Get consumption history
        $consumptionData = $this->getConsumptionHistory($meterId, 90); // Last 90 days
        
        // Generate prompt based on insight type
        $prompt = $this->buildPrompt($meterData, $consumptionData, $insightType);
        
        // Call AI provider
        $aiResponse = $this->callAiProvider($provider, $prompt);
        
        // Parse and store insights
        $insights = $this->parseAiResponse($aiResponse, $meterId, $insightType);
        
        return $insights;
    }
    
    /**
     * Get configured AI provider
     */
    private function getConfiguredProvider(): ?array
    {
        // Try to get settings from database first
        $dbApiKey = null;
        $dbModel = null;
        $dbEndpoint = null;
        $dbProvider = null;
        
        if ($this->db) {
            try {
                // Check for system settings
                $stmt = $this->db->query("
                    SELECT setting_key, setting_value 
                    FROM system_settings 
                    WHERE setting_key LIKE 'ai_%'
                ");
                $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                // Determine which provider is configured in database
                if (!empty($settings['ai_provider'])) {
                    $dbProvider = $settings['ai_provider'];
                }
            } catch (\PDOException $e) {
                // Table might not exist, continue with env vars
                error_log('Could not load AI settings from database: ' . $e->getMessage());
            }
        }
        
        $providers = [
            self::PROVIDER_OPENAI => [
                'api_key' => $settings['ai_openai_api_key'] ?? $_ENV['OPENAI_API_KEY'] ?? null,
                'endpoint' => 'https://api.openai.com/v1/chat/completions',
                'model' => $settings['ai_openai_model'] ?? $_ENV['OPENAI_MODEL'] ?? 'gpt-4o-mini'
            ],
            self::PROVIDER_ANTHROPIC => [
                'api_key' => $settings['ai_anthropic_api_key'] ?? $_ENV['ANTHROPIC_API_KEY'] ?? null,
                'endpoint' => 'https://api.anthropic.com/v1/messages',
                'model' => $settings['ai_anthropic_model'] ?? $_ENV['ANTHROPIC_MODEL'] ?? 'claude-3-5-sonnet-20241022'
            ],
            self::PROVIDER_GOOGLE => [
                'api_key' => $settings['ai_google_api_key'] ?? $_ENV['GOOGLE_AI_API_KEY'] ?? null,
                'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent',
                'model' => $settings['ai_google_model'] ?? $_ENV['GOOGLE_MODEL'] ?? 'gemini-pro'
            ],
            self::PROVIDER_AZURE => [
                'api_key' => $settings['ai_azure_api_key'] ?? $_ENV['AZURE_OPENAI_API_KEY'] ?? null,
                'endpoint' => $settings['ai_azure_endpoint'] ?? $_ENV['AZURE_OPENAI_ENDPOINT'] ?? null,
                'model' => $settings['ai_azure_model'] ?? $_ENV['AZURE_OPENAI_MODEL'] ?? 'gpt-4'
            ]
        ];
        
        // If a specific provider is set in database settings, try that first
        if ($dbProvider && isset($providers[$dbProvider]) && !empty($providers[$dbProvider]['api_key'])) {
            return array_merge($providers[$dbProvider], ['name' => $dbProvider]);
        }
        
        // Otherwise, return first configured provider
        foreach ($providers as $name => $config) {
            if (!empty($config['api_key'])) {
                return array_merge($config, ['name' => $name]);
            }
        }
        
        return null;
    }
    
    /**
     * Get meter data
     */
    private function getMeterData(int $meterId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT m.*, s.name as site_name, t.name as tariff_name, t.unit_rate, t.standing_charge
            FROM meters m
            LEFT JOIN sites s ON m.site_id = s.id
            LEFT JOIN tariffs t ON m.tariff_id = t.id
            WHERE m.id = ?
        ");
        $stmt->execute([$meterId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Get consumption history for a meter
     */
    private function getConsumptionHistory(int $meterId, int $days = 90): array
    {
        $stmt = $this->db->prepare("
            SELECT DATE(read_datetime) as date, SUM(value) as total_kwh
            FROM meter_readings
            WHERE meter_id = ? AND read_datetime >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(read_datetime)
            ORDER BY date DESC
        ");
        $stmt->execute([$meterId, $days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Build AI prompt based on meter data and insight type
     */
    private function buildPrompt(array $meterData, array $consumptionData, ?string $insightType): string
    {
        $mpan = $meterData['mpan'];
        $siteName = $meterData['site_name'] ?? 'Unknown Site';
        $energyType = $meterData['energy_type'];
        $tariffName = $meterData['tariff_name'] ?? 'No tariff';
        
        // Calculate statistics
        $totalKwh = array_sum(array_column($consumptionData, 'total_kwh'));
        $avgDaily = count($consumptionData) > 0 ? $totalKwh / count($consumptionData) : 0;
        $maxDaily = count($consumptionData) > 0 ? max(array_column($consumptionData, 'total_kwh')) : 0;
        $minDaily = count($consumptionData) > 0 ? min(array_column($consumptionData, 'total_kwh')) : 0;
        
        $basePrompt = "You are an energy management expert. Analyze the following energy consumption data and provide actionable insights.\n\n";
        $basePrompt .= "Meter Details:\n";
        $basePrompt .= "- MPAN: {$mpan}\n";
        $basePrompt .= "- Site: {$siteName}\n";
        $basePrompt .= "- Energy Type: {$energyType}\n";
        $basePrompt .= "- Current Tariff: {$tariffName}\n\n";
        
        $basePrompt .= "Consumption Statistics (last " . count($consumptionData) . " days):\n";
        $basePrompt .= "- Total: " . round($totalKwh, 2) . " kWh\n";
        $basePrompt .= "- Average Daily: " . round($avgDaily, 2) . " kWh\n";
        $basePrompt .= "- Maximum Daily: " . round($maxDaily, 2) . " kWh\n";
        $basePrompt .= "- Minimum Daily: " . round($minDaily, 2) . " kWh\n\n";
        
        // Add specific instructions based on insight type
        switch ($insightType) {
            case self::TYPE_CONSUMPTION_PATTERN:
                $basePrompt .= "Analyze consumption patterns and identify:\n";
                $basePrompt .= "1. Daily/weekly patterns\n2. Seasonal trends\n3. Baseload consumption\n4. Peak usage times\n";
                break;
            case self::TYPE_COST_OPTIMIZATION:
                $basePrompt .= "Provide cost optimization recommendations:\n";
                $basePrompt .= "1. Potential savings opportunities\n2. Time-of-use optimization\n3. Tariff switching suggestions\n";
                break;
            case self::TYPE_ANOMALY_DETECTION:
                $basePrompt .= "Identify anomalies and unusual patterns:\n";
                $basePrompt .= "1. Unexpected spikes or drops\n2. Missing data periods\n3. Potential equipment issues\n";
                break;
            case self::TYPE_CARBON_REDUCTION:
                $basePrompt .= "Suggest carbon reduction strategies:\n";
                $basePrompt .= "1. Peak demand reduction\n2. Load shifting opportunities\n3. Energy efficiency measures\n";
                break;
            default:
                $basePrompt .= "Provide comprehensive insights covering consumption patterns, cost optimization, and potential issues.\n";
        }
        
        $basePrompt .= "\nProvide your analysis in JSON format with the following structure:\n";
        $basePrompt .= "{\n";
        $basePrompt .= "  \"title\": \"Brief title for the insight\",\n";
        $basePrompt .= "  \"description\": \"Detailed analysis (2-3 paragraphs)\",\n";
        $basePrompt .= "  \"recommendations\": [\"List of actionable recommendations\"],\n";
        $basePrompt .= "  \"confidence_score\": 85,\n";
        $basePrompt .= "  \"priority\": \"high|medium|low\"\n";
        $basePrompt .= "}\n";
        
        return $basePrompt;
    }
    
    /**
     * Call AI provider API
     */
    private function callAiProvider(array $provider, string $prompt): string
    {
        $ch = curl_init();
        
        switch ($provider['name']) {
            case self::PROVIDER_OPENAI:
            case self::PROVIDER_AZURE:
                $headers = [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $provider['api_key']
                ];
                $data = [
                    'model' => $provider['model'],
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 1500
                ];
                break;
                
            case self::PROVIDER_ANTHROPIC:
                $headers = [
                    'Content-Type: application/json',
                    'x-api-key: ' . $provider['api_key'],
                    'anthropic-version: 2023-06-01'
                ];
                $data = [
                    'model' => $provider['model'],
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => 1500
                ];
                break;
                
            case self::PROVIDER_GOOGLE:
                $headers = [
                    'Content-Type: application/json'
                ];
                $endpoint = $provider['endpoint'] . '?key=' . $provider['api_key'];
                curl_setopt($ch, CURLOPT_URL, $endpoint);
                $data = [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ]
                ];
                break;
                
            default:
                throw new RuntimeException('Unsupported AI provider');
        }
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $provider['endpoint'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new RuntimeException("API call failed: {$error}");
        }
        
        if ($httpCode !== 200) {
            throw new RuntimeException("API returned error code {$httpCode}: {$response}");
        }
        
        return $this->extractResponseContent($provider['name'], $response);
    }
    
    /**
     * Extract content from AI provider response
     */
    private function extractResponseContent(string $providerName, string $response): string
    {
        $decoded = json_decode($response, true);
        
        switch ($providerName) {
            case self::PROVIDER_OPENAI:
            case self::PROVIDER_AZURE:
                return $decoded['choices'][0]['message']['content'] ?? '';
                
            case self::PROVIDER_ANTHROPIC:
                return $decoded['content'][0]['text'] ?? '';
                
            case self::PROVIDER_GOOGLE:
                return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
                
            default:
                return '';
        }
    }
    
    /**
     * Parse AI response and store insights
     */
    private function parseAiResponse(string $aiResponse, int $meterId, ?string $insightType): array
    {
        // Extract JSON from response (may be wrapped in markdown code blocks)
        $jsonMatch = [];
        if (preg_match('/```json\s*(.*?)\s*```/s', $aiResponse, $jsonMatch)) {
            $jsonStr = $jsonMatch[1];
        } else if (preg_match('/\{.*\}/s', $aiResponse, $jsonMatch)) {
            $jsonStr = $jsonMatch[0];
        } else {
            $jsonStr = $aiResponse;
        }
        
        $parsed = json_decode($jsonStr, true);
        if (!$parsed) {
            throw new RuntimeException('Failed to parse AI response as JSON');
        }
        
        // Store insight in database
        $stmt = $this->db->prepare("
            INSERT INTO ai_insights 
            (meter_id, insight_date, insight_type, title, description, recommendations, confidence_score, priority)
            VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?)
        ");
        
        $recommendations = json_encode($parsed['recommendations'] ?? []);
        $insightType = $insightType ?? self::TYPE_CONSUMPTION_PATTERN;
        
        $stmt->execute([
            $meterId,
            $insightType,
            $parsed['title'] ?? 'Energy Insight',
            $parsed['description'] ?? '',
            $recommendations,
            $parsed['confidence_score'] ?? 75,
            $parsed['priority'] ?? 'medium'
        ]);
        
        return [
            'id' => $this->db->lastInsertId(),
            'meter_id' => $meterId,
            'insight_type' => $insightType,
            'title' => $parsed['title'] ?? 'Energy Insight',
            'description' => $parsed['description'] ?? '',
            'recommendations' => $parsed['recommendations'] ?? [],
            'confidence_score' => $parsed['confidence_score'] ?? 75,
            'priority' => $parsed['priority'] ?? 'medium'
        ];
    }
    
    /**
     * Get insights for a meter
     */
    public function getInsightsForMeter(int $meterId, int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM ai_insights
            WHERE meter_id = ? AND is_dismissed = 0
            ORDER BY insight_date DESC, priority DESC
            LIMIT ?
        ");
        $stmt->execute([$meterId, $limit]);
        
        $insights = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode recommendations JSON
        foreach ($insights as &$insight) {
            $insight['recommendations'] = json_decode($insight['recommendations'] ?? '[]', true);
        }
        
        return $insights;
    }
    
    /**
     * Dismiss an insight
     */
    public function dismissInsight(int $insightId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE ai_insights
            SET is_dismissed = 1, dismissed_by = ?, dismissed_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$userId, $insightId]);
    }
    
    /**
     * Check if AI insights are configured
     */
    public function isConfigured(): bool
    {
        return $this->getConfiguredProvider() !== null;
    }
    
    /**
     * Get configured provider name
     */
    public function getConfiguredProviderName(): ?string
    {
        $provider = $this->getConfiguredProvider();
        return $provider ? $provider['name'] : null;
    }
}
