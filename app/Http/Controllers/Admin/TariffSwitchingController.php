<?php
/**
 * eclectyc-energy/app/Http/Controllers/Admin/TariffSwitchingController.php
 * Handles tariff switching analysis and recommendations.
 * Last updated: 07/11/2025
 */

namespace App\Http\Controllers\Admin;

use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Domain\Tariffs\TariffSwitchingAnalyzer;

class TariffSwitchingController
{
    private Twig $view;
    private ?PDO $pdo;

    public function __construct(Twig $view, ?PDO $pdo)
    {
        $this->view = $view;
        $this->pdo = $pdo;
    }

    /**
     * Display switching analysis form and results.
     */
    public function index(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            return $this->view->render($response, 'admin/tariff_switching.twig', [
                'page_title' => 'Tariff Switching Analysis',
                'error' => 'Database connection unavailable.',
            ]);
        }

        // Get list of meters for selection
        $meters = $this->getMeters();
        
        // Get all active tariffs for selection
        $tariffs = $this->getAllTariffs();

        // Get flash messages
        $flash = $_SESSION['switching_flash'] ?? null;
        unset($_SESSION['switching_flash']);

        return $this->view->render($response, 'admin/tariff_switching.twig', [
            'page_title' => 'Tariff Switching Analysis',
            'meters' => $meters,
            'tariffs' => $tariffs,
            'flash' => $flash,
        ]);
    }

    /**
     * Perform switching analysis for a specific meter.
     */
    public function analyze(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/tariff-switching');
        }

        $data = $request->getParsedBody() ?? [];
        $meterId = (int) ($data['meter_id'] ?? 0);
        $currentTariffId = (int) ($data['current_tariff_id'] ?? 0);
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';

        // Validate input
        if (!$meterId || !$currentTariffId || !$startDate || !$endDate) {
            $this->setFlash('error', 'All fields are required for analysis.');
            return $this->redirect($response, '/admin/tariff-switching');
        }

        // Perform analysis
        $analyzer = new TariffSwitchingAnalyzer($this->pdo);
        $analysis = $analyzer->analyzeSwitchingOpportunities(
            $meterId,
            $currentTariffId,
            $startDate,
            $endDate
        );

        // Save analysis if successful
        if (!isset($analysis['error'])) {
            $userId = $_SESSION['user_id'] ?? null;
            $analyzer->saveAnalysis($meterId, $analysis, $userId);
        }

        // Get meter details
        $meter = $this->getMeter($meterId);
        $meters = $this->getMeters();
        $tariffs = $this->getAllTariffs();

        return $this->view->render($response, 'admin/tariff_switching.twig', [
            'page_title' => 'Tariff Switching Analysis',
            'meters' => $meters,
            'tariffs' => $tariffs,
            'selected_meter' => $meter,
            'analysis' => $analysis,
            'form_data' => $data,
        ]);
    }

    /**
     * Display quick analysis for a meter (uses last 90 days).
     */
    public function quickAnalyze(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/tariff-switching');
        }

        $meterId = (int) $args['id'];
        
        $analyzer = new TariffSwitchingAnalyzer($this->pdo);
        $analysis = $analyzer->getDetailedAnalysis($meterId);

        // Save analysis if successful
        if (!isset($analysis['error'])) {
            $userId = $_SESSION['user_id'] ?? null;
            $analyzer->saveAnalysis($meterId, $analysis, $userId);
        }

        $meter = $this->getMeter($meterId);
        $meters = $this->getMeters();

        return $this->view->render($response, 'admin/tariff_switching.twig', [
            'page_title' => 'Tariff Switching Analysis',
            'meters' => $meters,
            'selected_meter' => $meter,
            'analysis' => $analysis,
        ]);
    }

    /**
     * Display historical analyses for a meter.
     */
    public function history(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/tariff-switching');
        }

        $meterId = (int) $args['id'];
        
        $analyzer = new TariffSwitchingAnalyzer($this->pdo);
        $analyses = $analyzer->getHistoricalAnalyses($meterId);
        $meter = $this->getMeter($meterId);

        return $this->view->render($response, 'admin/tariff_switching_history.twig', [
            'page_title' => 'Switching Analysis History',
            'meter' => $meter,
            'analyses' => $analyses,
        ]);
    }

    /**
     * Get all active meters with their current tariff information.
     */
    private function getMeters(): array
    {
        try {
            $stmt = $this->pdo->query('
                SELECT 
                    m.id,
                    m.mpan,
                    m.meter_type,
                    m.serial_number,
                    s.name as site_name,
                    sup.name as supplier_name,
                    t.id as current_tariff_id,
                    t.name as current_tariff_name
                FROM meters m
                LEFT JOIN sites s ON m.site_id = s.id
                LEFT JOIN suppliers sup ON m.supplier_id = sup.id
                LEFT JOIN tariffs t ON t.supplier_id = sup.id 
                    AND t.energy_type = m.meter_type
                    AND t.is_active = 1
                    AND (t.valid_to IS NULL OR t.valid_to >= CURDATE())
                WHERE m.is_active = 1
                GROUP BY m.id
                ORDER BY s.name, m.mpan
            ');
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('Failed to get meters: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a single meter with details.
     */
    private function getMeter(int $meterId): ?array
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT 
                    m.*,
                    s.name as site_name,
                    sup.name as supplier_name,
                    t.id as current_tariff_id,
                    t.name as current_tariff_name
                FROM meters m
                LEFT JOIN sites s ON m.site_id = s.id
                LEFT JOIN suppliers sup ON m.supplier_id = sup.id
                LEFT JOIN tariffs t ON t.supplier_id = sup.id 
                    AND t.energy_type = m.meter_type
                    AND t.is_active = 1
                    AND (t.valid_to IS NULL OR t.valid_to >= CURDATE())
                WHERE m.id = :id
                LIMIT 1
            ');
            
            $stmt->execute(['id' => $meterId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
        } catch (PDOException $e) {
            error_log('Failed to get meter: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all active tariffs.
     */
    private function getAllTariffs(): array
    {
        try {
            $stmt = $this->pdo->query('
                SELECT 
                    t.id,
                    t.name,
                    t.energy_type,
                    t.tariff_type,
                    t.unit_rate,
                    t.standing_charge,
                    s.name as supplier_name
                FROM tariffs t
                LEFT JOIN suppliers s ON t.supplier_id = s.id
                WHERE t.is_active = 1
                    AND (t.valid_to IS NULL OR t.valid_to >= CURDATE())
                ORDER BY s.name, t.energy_type, t.name
            ');
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('Failed to get tariffs: ' . $e->getMessage());
            return [];
        }
    }

    private function setFlash(string $type, string $message): void
    {
        $_SESSION['switching_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    private function redirect(Response $response, string $path): Response
    {
        return $response->withHeader('Location', $path)->withStatus(302);
    }
}
