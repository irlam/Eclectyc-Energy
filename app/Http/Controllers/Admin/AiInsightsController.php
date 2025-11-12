<?php

namespace App\Http\Controllers\Admin;

use App\Services\AiInsightsService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class AiInsightsController
{
    private PDO $db;
    private Twig $view;
    private AiInsightsService $aiService;
    
    public function __construct(PDO $db, Twig $view)
    {
        $this->db = $db;
        $this->view = $view;
        $this->aiService = new AiInsightsService($db);
    }
    
    /**
     * Show AI insights dashboard
     */
    public function index(Request $request, Response $response): Response
    {
        // Check if AI is configured
        $isConfigured = $this->aiService->isConfigured();
        $providerName = $this->aiService->getConfiguredProviderName();
        
        // Get all meters with their latest insights
        $stmt = $this->db->query("
            SELECT 
                m.id, m.mpan, m.energy_type,
                s.name as site_name,
                COUNT(DISTINCT ai.id) as insight_count,
                MAX(ai.insight_date) as last_insight_date
            FROM meters m
            LEFT JOIN sites s ON m.site_id = s.id
            LEFT JOIN ai_insights ai ON m.id = ai.meter_id AND ai.is_dismissed = 0
            GROUP BY m.id
            ORDER BY insight_count DESC, m.mpan
        ");
        $meters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get recent insights across all meters
        $stmt = $this->db->query("
            SELECT 
                ai.*,
                m.mpan,
                s.name as site_name
            FROM ai_insights ai
            JOIN meters m ON ai.meter_id = m.id
            LEFT JOIN sites s ON m.site_id = s.id
            WHERE ai.is_dismissed = 0
            ORDER BY ai.insight_date DESC, ai.priority DESC
            LIMIT 20
        ");
        $recentInsights = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode recommendations
        foreach ($recentInsights as &$insight) {
            $insight['recommendations'] = json_decode($insight['recommendations'] ?? '[]', true);
        }
        
        return $this->view->render($response, 'admin/ai_insights/index.twig', [
            'title' => 'AI Insights',
            'is_configured' => $isConfigured,
            'provider_name' => $providerName,
            'meters' => $meters,
            'recent_insights' => $recentInsights
        ]);
    }
    
    /**
     * Generate insights for a meter
     */
    public function generate(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $meterId = (int) ($data['meter_id'] ?? 0);
        $insightType = $data['insight_type'] ?? null;
        
        try {
            if (!$this->aiService->isConfigured()) {
                throw new \RuntimeException('AI provider not configured. Please add an API key in Settings.');
            }
            
            $insight = $this->aiService->generateInsightsForMeter($meterId, $insightType);
            
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'AI insight generated successfully!'
            ];
            
            // Return JSON for AJAX requests
            if ($request->hasHeader('X-Requested-With')) {
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'insight' => $insight
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }
            
            return $response
                ->withHeader('Location', '/admin/ai-insights')
                ->withStatus(302);
                
        } catch (\Exception $e) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'Failed to generate insight: ' . $e->getMessage()
            ];
            
            if ($request->hasHeader('X-Requested-With')) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]));
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(400);
            }
            
            return $response
                ->withHeader('Location', '/admin/ai-insights')
                ->withStatus(302);
        }
    }
    
    /**
     * View insights for a specific meter
     */
    public function viewMeter(Request $request, Response $response, array $args): Response
    {
        $meterId = (int) $args['id'];
        
        // Get meter details
        $stmt = $this->db->prepare("
            SELECT m.*, s.name as site_name
            FROM meters m
            LEFT JOIN sites s ON m.site_id = s.id
            WHERE m.id = ?
        ");
        $stmt->execute([$meterId]);
        $meter = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$meter) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'Meter not found'
            ];
            return $response
                ->withHeader('Location', '/admin/ai-insights')
                ->withStatus(302);
        }
        
        // Get insights for this meter
        $insights = $this->aiService->getInsightsForMeter($meterId, 50);
        
        return $this->view->render($response, 'admin/ai_insights/meter.twig', [
            'title' => 'AI Insights - ' . $meter['mpan'],
            'meter' => $meter,
            'insights' => $insights,
            'is_configured' => $this->aiService->isConfigured()
        ]);
    }
    
    /**
     * Dismiss an insight
     */
    public function dismiss(Request $request, Response $response, array $args): Response
    {
        $insightId = (int) $args['id'];
        $userId = $_SESSION['user_id'] ?? 0;
        
        try {
            $this->aiService->dismissInsight($insightId, $userId);
            
            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Insight dismissed'
            ];
            
        } catch (\Exception $e) {
            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'Failed to dismiss insight: ' . $e->getMessage()
            ];
        }
        
        return $response
            ->withHeader('Location', $_SERVER['HTTP_REFERER'] ?? '/admin/ai-insights')
            ->withStatus(302);
    }
    
    /**
     * Settings page for AI configuration
     */
    public function settings(Request $request, Response $response): Response
    {
        // Get current configuration status
        $providers = [
            'openai' => [
                'name' => 'OpenAI (GPT-4)',
                'configured' => !empty($_ENV['OPENAI_API_KEY']),
                'url' => 'https://platform.openai.com/api-keys'
            ],
            'anthropic' => [
                'name' => 'Anthropic (Claude)',
                'configured' => !empty($_ENV['ANTHROPIC_API_KEY']),
                'url' => 'https://console.anthropic.com/'
            ],
            'google' => [
                'name' => 'Google AI (Gemini)',
                'configured' => !empty($_ENV['GOOGLE_AI_API_KEY']),
                'url' => 'https://makersuite.google.com/app/apikey'
            ],
            'azure' => [
                'name' => 'Azure OpenAI',
                'configured' => !empty($_ENV['AZURE_OPENAI_API_KEY']),
                'url' => 'https://portal.azure.com/'
            ]
        ];
        
        return $this->view->render($response, 'admin/ai_insights/settings.twig', [
            'title' => 'AI Insights Settings',
            'providers' => $providers,
            'active_provider' => $this->aiService->getConfiguredProviderName()
        ]);
    }
}
