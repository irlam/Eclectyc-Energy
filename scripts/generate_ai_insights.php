#!/usr/bin/env php
<?php
/**
 * Generate AI Insights for Meters
 * 
 * This script generates AI-powered insights for specified meters or all meters.
 * Can be run manually or scheduled via cron for periodic insight generation.
 * 
 * Usage:
 *   php scripts/generate_ai_insights.php --meter-id 123
 *   php scripts/generate_ai_insights.php --all
 *   php scripts/generate_ai_insights.php --all --type cost_optimization
 *   php scripts/generate_ai_insights.php --site-id 5
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Config\Database;
use App\Services\AiInsightsService;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Parse command line arguments
$options = getopt('', [
    'meter-id:',
    'site-id:',
    'all',
    'type:',
    'help',
    'verbose'
]);

if (isset($options['help'])) {
    echo "AI Insights Generator\n\n";
    echo "Usage:\n";
    echo "  --meter-id <id>    Generate insights for specific meter\n";
    echo "  --site-id <id>     Generate insights for all meters at a site\n";
    echo "  --all              Generate insights for all meters\n";
    echo "  --type <type>      Specific insight type (optional)\n";
    echo "                     Types: consumption_pattern, cost_optimization,\n";
    echo "                            anomaly_detection, carbon_reduction,\n";
    echo "                            predictive_maintenance\n";
    echo "  --verbose          Show detailed output\n";
    echo "  --help             Show this help message\n\n";
    echo "Examples:\n";
    echo "  php scripts/generate_ai_insights.php --meter-id 123\n";
    echo "  php scripts/generate_ai_insights.php --all --type cost_optimization\n";
    echo "  php scripts/generate_ai_insights.php --site-id 5 --verbose\n";
    exit(0);
}

$verbose = isset($options['verbose']);
$insightType = $options['type'] ?? null;

try {
    // Connect to database
    $db = Database::getConnection();
    $aiService = new AiInsightsService($db);
    
    // Check if AI is configured
    if (!$aiService->isConfigured()) {
        echo "ERROR: No AI provider configured.\n";
        echo "Please add an API key to your .env file.\n";
        echo "See docs/ai_insights.md for setup instructions.\n";
        exit(1);
    }
    
    if ($verbose) {
        $provider = $aiService->getConfiguredProviderName();
        echo "Using AI provider: " . strtoupper($provider) . "\n\n";
    }
    
    // Get list of meters to process
    $meters = [];
    
    if (isset($options['meter-id'])) {
        $meterId = (int) $options['meter-id'];
        $stmt = $db->prepare("SELECT id, mpan FROM meters WHERE id = ?");
        $stmt->execute([$meterId]);
        $meter = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($meter) {
            $meters[] = $meter;
        } else {
            echo "ERROR: Meter ID {$meterId} not found.\n";
            exit(1);
        }
    } elseif (isset($options['site-id'])) {
        $siteId = (int) $options['site-id'];
        $stmt = $db->prepare("SELECT id, mpan FROM meters WHERE site_id = ? AND is_active = 1");
        $stmt->execute([$siteId]);
        $meters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($meters)) {
            echo "ERROR: No active meters found for site ID {$siteId}.\n";
            exit(1);
        }
    } elseif (isset($options['all'])) {
        $stmt = $db->query("SELECT id, mpan FROM meters WHERE is_active = 1 ORDER BY id");
        $meters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($meters)) {
            echo "ERROR: No active meters found.\n";
            exit(1);
        }
    } else {
        echo "ERROR: You must specify --meter-id, --site-id, or --all\n";
        echo "Run with --help for usage information.\n";
        exit(1);
    }
    
    $total = count($meters);
    $success = 0;
    $failed = 0;
    
    echo "Generating insights for {$total} meter(s)...\n";
    if ($insightType) {
        echo "Insight type: " . str_replace('_', ' ', ucwords($insightType, '_')) . "\n";
    }
    echo "\n";
    
    foreach ($meters as $index => $meter) {
        $meterId = $meter['id'];
        $mpan = $meter['mpan'];
        $num = $index + 1;
        
        if ($verbose) {
            echo "[{$num}/{$total}] Processing meter {$mpan} (ID: {$meterId})... ";
        } else {
            echo ".";
        }
        
        try {
            $insight = $aiService->generateInsightsForMeter($meterId, $insightType);
            $success++;
            
            if ($verbose) {
                echo "✓ Success\n";
                echo "  Title: {$insight['title']}\n";
                echo "  Priority: {$insight['priority']}\n";
                echo "  Confidence: {$insight['confidence_score']}%\n\n";
            }
        } catch (Exception $e) {
            $failed++;
            
            if ($verbose) {
                echo "✗ Failed\n";
                echo "  Error: {$e->getMessage()}\n\n";
            }
        }
        
        // Rate limiting - wait 1 second between requests to avoid API throttling
        if ($index < $total - 1) {
            sleep(1);
        }
    }
    
    if (!$verbose) {
        echo "\n";
    }
    
    echo "\nSummary:\n";
    echo "  Total meters: {$total}\n";
    echo "  Successful: {$success}\n";
    echo "  Failed: {$failed}\n";
    
    if ($success > 0) {
        echo "\n✓ AI insights generated successfully!\n";
        echo "View insights at: /admin/ai-insights\n";
    }
    
    exit($failed > 0 ? 1 : 0);
    
} catch (Exception $e) {
    echo "ERROR: {$e->getMessage()}\n";
    if ($verbose) {
        echo "\nStack trace:\n{$e->getTraceAsString()}\n";
    }
    exit(1);
}
