<?php
/**
 * eclectyc-energy/app/http/Controllers/DashboardController.php
 * Renders the main dashboard page
 */

namespace App\Http\Controllers;

use App\Domain\External\ExternalDataService;
use App\Services\CarbonIntensityService;
use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class DashboardController
{
    private Twig $view;
    private ?\PDO $pdo;

    public function __construct(Twig $view, ?\PDO $pdo)
    {
        $this->view = $view;
        $this->pdo = $pdo;
    }

    public function index(Request $request, Response $response): Response
    {
        $stats = [
            'total_sites' => 0,
            'total_meters' => 0,
            'total_readings' => 0,
            'last_import' => 'Never',
            'week_consumption' => 0.0,
            'month_consumption' => 0.0,
            'latest_aggregation_date' => null,
            'coverage_pct' => 0,
        ];

        $trend = [];
        $recentActivity = [];
        $carbonIntensity = null;

        if ($this->pdo) {
            try {
                // Initialize carbon intensity service
                $externalDataService = new ExternalDataService($this->pdo);
                $carbonService = new CarbonIntensityService($externalDataService);
                $carbonIntensity = $carbonService->getDashboardSummary();
            } catch (\Throwable $e) {
                // Carbon intensity is optional - don't break dashboard if it fails
                error_log("Carbon intensity fetch failed: " . $e->getMessage());
            }

            try {
                $stmt = $this->pdo->query('SELECT COUNT(*) as count FROM sites');
                $stats['total_sites'] = (int) ($stmt->fetch()['count'] ?? 0);

                $stmt = $this->pdo->query('SELECT COUNT(*) as count FROM meters');
                $stats['total_meters'] = (int) ($stmt->fetch()['count'] ?? 0);

                $stmt = $this->pdo->query('SELECT COUNT(*) as count FROM meter_readings');
                $stats['total_readings'] = (int) ($stmt->fetch()['count'] ?? 0);

                $stmt = $this->pdo->query('SELECT MAX(created_at) as last FROM meter_readings');
                $last = $stmt->fetch()['last'] ?? null;
                if ($last) {
                    $stats['last_import'] = date('d/m/Y H:i:s', strtotime($last));
                }
            } catch (\Throwable $e) {
                // Ignore errors and use default stats
            }

            try {
                $today = new DateTimeImmutable('today');
                $weekStart = $today->modify('monday this week');
                if ($weekStart > $today) {
                    $weekStart = $weekStart->modify('-7 days');
                }
                $weekEnd = $weekStart->add(new DateInterval('P6D'));

                $trendStart = $today->sub(new DateInterval('P6D'));
                $trendMap = [];
                $period = new DatePeriod($trendStart, new DateInterval('P1D'), $today->add(new DateInterval('P1D')));
                foreach ($period as $date) {
                    $trendMap[$date->format('Y-m-d')] = 0.0;
                }

                $trendStmt = $this->pdo->prepare('
                    SELECT date, SUM(total_consumption) AS total
                    FROM daily_aggregations
                    WHERE date BETWEEN :start AND :end
                    GROUP BY date
                    ORDER BY date ASC
                ');
                $trendStmt->execute([
                    'start' => $trendStart->format('Y-m-d'),
                    'end' => $today->format('Y-m-d'),
                ]);

                foreach ($trendStmt->fetchAll() as $row) {
                    $trendMap[$row['date']] = (float) ($row['total'] ?? 0.0);
                }

                foreach ($trendMap as $date => $total) {
                    $trend[] = [
                        'date' => $date,
                        'total' => $total,
                    ];
                }

                $weekStmt = $this->pdo->prepare('
                    SELECT SUM(total_consumption) AS total
                    FROM weekly_aggregations
                    WHERE week_start = :start
                ');
                $weekStmt->execute(['start' => $weekStart->format('Y-m-d')]);
                $stats['week_consumption'] = (float) ($weekStmt->fetch()['total'] ?? 0.0);

                if ($stats['week_consumption'] === 0.0) {
                    $weekFallback = $this->pdo->prepare('
                        SELECT SUM(total_consumption) AS total
                        FROM daily_aggregations
                        WHERE date BETWEEN :start AND :end
                    ');
                    $weekFallback->execute([
                        'start' => $weekStart->format('Y-m-d'),
                        'end' => $weekEnd->format('Y-m-d'),
                    ]);
                    $stats['week_consumption'] = (float) ($weekFallback->fetch()['total'] ?? 0.0);
                }

                $monthStart = $today->modify('first day of this month');
                $monthStmt = $this->pdo->prepare('
                    SELECT SUM(total_consumption) AS total
                    FROM monthly_aggregations
                    WHERE month_start = :start
                ');
                $monthStmt->execute(['start' => $monthStart->format('Y-m-d')]);
                $stats['month_consumption'] = (float) ($monthStmt->fetch()['total'] ?? 0.0);

                if ($stats['month_consumption'] === 0.0) {
                    $monthFallback = $this->pdo->prepare('
                        SELECT SUM(total_consumption) AS total
                        FROM daily_aggregations
                        WHERE date BETWEEN :start AND :end
                    ');
                    $monthFallback->execute([
                        'start' => $monthStart->format('Y-m-d'),
                        'end' => $today->format('Y-m-d'),
                    ]);
                    $stats['month_consumption'] = (float) ($monthFallback->fetch()['total'] ?? 0.0);
                }

                $latestAggregationStmt = $this->pdo->query('
                    SELECT date, COUNT(*) AS meter_count
                    FROM daily_aggregations
                    ORDER BY date DESC
                    LIMIT 1
                ');
                $latestAggregation = $latestAggregationStmt->fetch();
                if ($latestAggregation) {
                    $stats['latest_aggregation_date'] = $latestAggregation['date'];
                    $meterCount = (int) ($latestAggregation['meter_count'] ?? 0);
                    if ($stats['total_meters'] > 0) {
                        $stats['coverage_pct'] = round(($meterCount / $stats['total_meters']) * 100);
                    }
                }
            } catch (\Throwable $e) {
                // Ignore aggregation errors for now
            }

            try {
                $activityStmt = $this->pdo->query('
                    SELECT created_at, action, entity_type, user_id
                    FROM audit_logs
                    ORDER BY created_at DESC
                    LIMIT 5
                ');
                $recentActivity = $activityStmt->fetchAll() ?: [];
            } catch (\Throwable $e) {
                $recentActivity = [];
            }
        }

        return $this->view->render($response, 'dashboard.twig', [
            'page_title' => 'Dashboard',
            'stats' => $stats,
            'trend' => $trend,
            'recent_activity' => $recentActivity,
            'carbon_intensity' => $carbonIntensity,
        ]);
    }
}
