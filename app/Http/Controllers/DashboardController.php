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

    /**
     * Build WHERE clause and params for site filtering
     * 
     * @param array $siteIds Accessible site IDs
     * @param string $tableAlias Table alias (e.g., 'm' for meters)
     * @param string $columnName Column name for site_id (default: 'site_id')
     * @return array ['where' => string, 'params' => array]
     */
    private function buildSiteFilter(array $siteIds, string $tableAlias = '', string $columnName = 'site_id'): array
    {
        if (empty($siteIds)) {
            return ['where' => '', 'params' => []];
        }

        $column = $tableAlias ? "$tableAlias.$columnName" : $columnName;
        $placeholders = implode(',', array_fill(0, count($siteIds), '?'));
        
        return [
            'where' => " WHERE $column IN ($placeholders)",
            'params' => $siteIds
        ];
    }

    /**
     * Build query for reading type data with site filtering
     * 
     * @param array $siteIds Accessible site IDs
     * @param string $dateCondition Date condition (e.g., '>= ?' or 'BETWEEN ? AND ?')
     * @return array ['query' => string, 'params' => array]
     */
    private function buildReadingTypeQuery(array $siteIds, string $dateCondition): array
    {
        if (!empty($siteIds)) {
            $placeholders = implode(',', array_fill(0, count($siteIds), '?'));
            $query = '
                SELECT 
                    reading_type,
                    SUM(reading_value) as total_kwh
                FROM meter_readings mr
                INNER JOIN meters m ON m.id = mr.meter_id
                WHERE m.site_id IN (' . $placeholders . ')
                AND mr.reading_date ' . $dateCondition . '
                GROUP BY reading_type
            ';
            return ['query' => $query, 'needs_site_params' => true];
        } else {
            $query = '
                SELECT 
                    reading_type,
                    SUM(reading_value) as total_kwh
                FROM meter_readings
                WHERE reading_date ' . $dateCondition . '
                GROUP BY reading_type
            ';
            return ['query' => $query, 'needs_site_params' => false];
        }
    }

    public function index(Request $request, Response $response): Response
    {
        // Get current user from session
        $user = $_SESSION['user'] ?? null;
        $userId = $user['id'] ?? null;
        
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
        $yesterdayConsumption = null;
        $healthReport = [
            'sites_with_data' => 0,
            'sites_without_data' => 0,
            'sites_total' => 0,
            'actual_readings_pct' => 0,
            'estimated_readings_pct' => 0,
            'sites_list' => [],
        ];
        $dataQuality = [
            'current_month' => [
                'total_kwh' => 0.0,
                'actual_kwh' => 0.0,
                'estimated_kwh' => 0.0,
                'actual_pct' => 0,
            ],
            'previous_month' => [
                'total_kwh' => 0.0,
                'actual_kwh' => 0.0,
                'estimated_kwh' => 0.0,
                'actual_pct' => 0,
            ],
        ];

        if ($this->pdo) {
            // Get accessible site IDs for the user (respecting hierarchical access)
            $accessibleSiteIds = [];
            if ($userId) {
                $userModel = \App\Models\User::find($userId);
                if ($userModel) {
                    $accessibleSiteIds = $userModel->getAccessibleSiteIds();
                }
            }
            
            // Build WHERE clause for site filtering
            $siteFilter = '';
            $siteFilterParams = [];
            if (!empty($accessibleSiteIds)) {
                $placeholders = implode(',', array_fill(0, count($accessibleSiteIds), '?'));
                $siteFilter = " WHERE s.id IN ($placeholders)";
                $siteFilterParams = $accessibleSiteIds;
            }
            
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
                // Count sites user has access to
                if (!empty($accessibleSiteIds)) {
                    $placeholders = implode(',', array_fill(0, count($accessibleSiteIds), '?'));
                    $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM sites WHERE id IN ($placeholders)");
                    $stmt->execute($accessibleSiteIds);
                } else {
                    $stmt = $this->pdo->query('SELECT COUNT(*) as count FROM sites');
                }
                $stats['total_sites'] = (int) ($stmt->fetch()['count'] ?? 0);

                // Count meters for accessible sites
                if (!empty($accessibleSiteIds)) {
                    $placeholders = implode(',', array_fill(0, count($accessibleSiteIds), '?'));
                    $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM meters WHERE site_id IN ($placeholders)");
                    $stmt->execute($accessibleSiteIds);
                } else {
                    $stmt = $this->pdo->query('SELECT COUNT(*) as count FROM meters');
                }
                $stats['total_meters'] = (int) ($stmt->fetch()['count'] ?? 0);

                // Count readings for accessible sites
                if (!empty($accessibleSiteIds)) {
                    $placeholders = implode(',', array_fill(0, count($accessibleSiteIds), '?'));
                    $stmt = $this->pdo->prepare("
                        SELECT COUNT(*) as count 
                        FROM meter_readings mr
                        INNER JOIN meters m ON m.id = mr.meter_id
                        WHERE m.site_id IN ($placeholders)
                    ");
                    $stmt->execute($accessibleSiteIds);
                } else {
                    $stmt = $this->pdo->query('SELECT COUNT(*) as count FROM meter_readings');
                }
                $stats['total_readings'] = (int) ($stmt->fetch()['count'] ?? 0);

                // Get last import for accessible sites
                if (!empty($accessibleSiteIds)) {
                    $placeholders = implode(',', array_fill(0, count($accessibleSiteIds), '?'));
                    $stmt = $this->pdo->prepare("
                        SELECT MAX(mr.created_at) as last 
                        FROM meter_readings mr
                        INNER JOIN meters m ON m.id = mr.meter_id
                        WHERE m.site_id IN ($placeholders)
                    ");
                    $stmt->execute($accessibleSiteIds);
                } else {
                    $stmt = $this->pdo->query('SELECT MAX(created_at) as last FROM meter_readings');
                }
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

                // Data quality stats: Actual vs Estimated
            try {
                $today = new DateTimeImmutable('today');
                $yesterday = $today->modify('-1 day');
                $currentMonthStart = $today->modify('first day of this month');
                $previousMonthStart = $currentMonthStart->modify('-1 month');
                $previousMonthEnd = $currentMonthStart->modify('-1 day');

                // Yesterday's consumption widget
                if (!empty($accessibleSiteIds)) {
                    $placeholders = implode(',', array_fill(0, count($accessibleSiteIds), '?'));
                    $stmt = $this->pdo->prepare("
                        SELECT 
                            SUM(da.total_consumption) as total,
                            COUNT(DISTINCT da.meter_id) as meter_count
                        FROM daily_aggregations da
                        INNER JOIN meters m ON m.id = da.meter_id
                        WHERE da.date = ? AND m.site_id IN ($placeholders)
                    ");
                    $params = array_merge([$yesterday->format('Y-m-d')], $accessibleSiteIds);
                    $stmt->execute($params);
                } else {
                    $stmt = $this->pdo->prepare('
                        SELECT 
                            SUM(total_consumption) as total,
                            COUNT(DISTINCT meter_id) as meter_count
                        FROM daily_aggregations
                        WHERE date = ?
                    ');
                    $stmt->execute([$yesterday->format('Y-m-d')]);
                }
                $yesterdayData = $stmt->fetch();
                $yesterdayConsumption = [
                    'date' => $yesterday->format('Y-m-d'),
                    'total_kwh' => (float)($yesterdayData['total'] ?? 0),
                    'meter_count' => (int)($yesterdayData['meter_count'] ?? 0),
                ];

                // Health Report widget - Sites with/without data
                if (!empty($accessibleSiteIds)) {
                    $placeholders = implode(',', array_fill(0, count($accessibleSiteIds), '?'));
                    
                    // Get all accessible sites with their data status
                    $stmt = $this->pdo->prepare("
                        SELECT 
                            s.id,
                            s.name,
                            c.name as company_name,
                            COUNT(DISTINCT m.id) as meter_count,
                            COUNT(DISTINCT da.id) as has_data
                        FROM sites s
                        LEFT JOIN companies c ON c.id = s.company_id
                        LEFT JOIN meters m ON m.site_id = s.id
                        LEFT JOIN daily_aggregations da ON da.meter_id = m.id 
                            AND da.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                        WHERE s.id IN ($placeholders)
                        GROUP BY s.id, s.name, c.name
                        ORDER BY c.name, s.name
                    ");
                    $stmt->execute($accessibleSiteIds);
                } else {
                    $stmt = $this->pdo->query("
                        SELECT 
                            s.id,
                            s.name,
                            c.name as company_name,
                            COUNT(DISTINCT m.id) as meter_count,
                            COUNT(DISTINCT da.id) as has_data
                        FROM sites s
                        LEFT JOIN companies c ON c.id = s.company_id
                        LEFT JOIN meters m ON m.site_id = s.id
                        LEFT JOIN daily_aggregations da ON da.meter_id = m.id 
                            AND da.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                        GROUP BY s.id, s.name, c.name
                        ORDER BY c.name, s.name
                    ");
                }
                $sitesList = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                $sitesWithData = 0;
                $sitesWithoutData = 0;
                foreach ($sitesList as &$site) {
                    $site['has_recent_data'] = (int)$site['has_data'] > 0;
                    if ($site['has_recent_data']) {
                        $sitesWithData++;
                    } else {
                        $sitesWithoutData++;
                    }
                }
                
                $healthReport['sites_with_data'] = $sitesWithData;
                $healthReport['sites_without_data'] = $sitesWithoutData;
                $healthReport['sites_total'] = $sitesWithData + $sitesWithoutData;
                $healthReport['sites_list'] = $sitesList;

                // Current month - using helper method for cleaner query building
                $currentMonthQuery = $this->buildReadingTypeQuery($accessibleSiteIds, '>= ?');
                $currentMonthStmt = $this->pdo->prepare($currentMonthQuery['query']);
                
                $params = [];
                if ($currentMonthQuery['needs_site_params']) {
                    $params = array_merge($accessibleSiteIds, [$currentMonthStart->format('Y-m-d')]);
                } else {
                    $params = [$currentMonthStart->format('Y-m-d')];
                }
                $currentMonthStmt->execute($params);
                $currentMonthData = $currentMonthStmt->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];
                
                $dataQuality['current_month']['actual_kwh'] = (float) ($currentMonthData['actual'] ?? 0);
                $dataQuality['current_month']['estimated_kwh'] = (float) ($currentMonthData['estimated'] ?? 0);
                $dataQuality['current_month']['total_kwh'] = $dataQuality['current_month']['actual_kwh'] + $dataQuality['current_month']['estimated_kwh'];
                
                if ($dataQuality['current_month']['total_kwh'] > 0) {
                    $dataQuality['current_month']['actual_pct'] = round(
                        ($dataQuality['current_month']['actual_kwh'] / $dataQuality['current_month']['total_kwh']) * 100
                    );
                }

                // Previous month - using helper method for cleaner query building
                $previousMonthQuery = $this->buildReadingTypeQuery($accessibleSiteIds, 'BETWEEN ? AND ?');
                $previousMonthStmt = $this->pdo->prepare($previousMonthQuery['query']);
                
                $params = [];
                if ($previousMonthQuery['needs_site_params']) {
                    $params = array_merge($accessibleSiteIds, [$previousMonthStart->format('Y-m-d'), $previousMonthEnd->format('Y-m-d')]);
                } else {
                    $params = [$previousMonthStart->format('Y-m-d'), $previousMonthEnd->format('Y-m-d')];
                }
                $previousMonthStmt->execute($params);
                $previousMonthData = $previousMonthStmt->fetchAll(\PDO::FETCH_KEY_PAIR) ?: [];
                
                $dataQuality['previous_month']['actual_kwh'] = (float) ($previousMonthData['actual'] ?? 0);
                $dataQuality['previous_month']['estimated_kwh'] = (float) ($previousMonthData['estimated'] ?? 0);
                $dataQuality['previous_month']['total_kwh'] = $dataQuality['previous_month']['actual_kwh'] + $dataQuality['previous_month']['estimated_kwh'];
                
                if ($dataQuality['previous_month']['total_kwh'] > 0) {
                    $dataQuality['previous_month']['actual_pct'] = round(
                        ($dataQuality['previous_month']['actual_kwh'] / $dataQuality['previous_month']['total_kwh']) * 100
                    );
                }
                
                // Calculate overall reading type percentages for health report
                $totalActual = $dataQuality['current_month']['actual_kwh'] + $dataQuality['previous_month']['actual_kwh'];
                $totalEstimated = $dataQuality['current_month']['estimated_kwh'] + $dataQuality['previous_month']['estimated_kwh'];
                $totalAll = $totalActual + $totalEstimated;
                
                if ($totalAll > 0) {
                    $healthReport['actual_readings_pct'] = round(($totalActual / $totalAll) * 100);
                    $healthReport['estimated_readings_pct'] = round(($totalEstimated / $totalAll) * 100);
                }
            } catch (\Throwable $e) {
                // Ignore data quality errors
                error_log("Data quality stats failed: " . $e->getMessage());
            }
        }

        return $this->view->render($response, 'dashboard.twig', [
            'page_title' => 'Dashboard',
            'stats' => $stats,
            'trend' => $trend,
            'recent_activity' => $recentActivity,
            'carbon_intensity' => $carbonIntensity,
            'data_quality' => $dataQuality,
            'yesterday_consumption' => $yesterdayConsumption,
            'health_report' => $healthReport,
        ]);
    }
}
