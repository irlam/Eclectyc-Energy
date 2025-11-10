<?php
/**
 * eclectyc-energy/app/Http/Controllers/ReportsController.php
 * Handles reporting dashboards for consumption and cost analytics.
 */

namespace App\Http\Controllers;

use DateTimeImmutable;
use DateInterval;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class ReportsController
{
    private Twig $view;
    private ?PDO $pdo;

    public function __construct(Twig $view, ?PDO $pdo)
    {
        $this->view = $view;
        $this->pdo = $pdo;
    }

    public function consumption(Request $request, Response $response): Response
    {
        // Get current user and their accessible sites
        $user = $_SESSION['user'] ?? null;
        $userId = $user['id'] ?? null;
        $accessibleSiteIds = [];
        
        if ($userId) {
            $userModel = \App\Models\User::find($userId);
            if ($userModel) {
                $accessibleSiteIds = $userModel->getAccessibleSiteIds();
            }
        }
        
        $period = $this->resolvePeriod($request, 7);
        $showPerMetric = isset($request->getQueryParams()['per_metric']) && $request->getQueryParams()['per_metric'] === '1';
        
        $reportData = [
            'rows' => [],
            'totalConsumption' => 0.0,
            'hasMetricData' => false,
        ];

        if ($this->pdo) {
            try {
                // Build WHERE clause for site filtering
                $siteFilter = '';
                $params = [
                    'start' => $period['start']->format('Y-m-d'),
                    'end' => $period['end']->format('Y-m-d'),
                ];
                
                if (!empty($accessibleSiteIds)) {
                    $placeholders = implode(',', array_fill(0, count($accessibleSiteIds), '?'));
                    $siteFilter = " AND s.id IN ($placeholders)";
                    // Add site IDs to params array
                    foreach ($accessibleSiteIds as $siteId) {
                        $params[] = $siteId;
                    }
                }
                
                // Modified query to show only accessible sites
                // Now also includes metric variable information
                $stmt = $this->pdo->prepare('
                    SELECT
                        s.id AS site_id,
                        s.name AS site_name,
                        COUNT(DISTINCT CASE WHEN da.date BETWEEN :start AND :end THEN m.id END) AS meter_count,
                        COALESCE(SUM(CASE WHEN da.date BETWEEN :start AND :end THEN da.total_consumption END), 0) AS total_consumption,
                        MIN(CASE WHEN da.date BETWEEN :start AND :end THEN da.date END) AS first_reading,
                        MAX(CASE WHEN da.date BETWEEN :start AND :end THEN da.date END) AS last_reading,
                        GROUP_CONCAT(DISTINCT CASE WHEN m.metric_variable_name IS NOT NULL THEN m.metric_variable_name END) AS metric_names,
                        SUM(CASE WHEN m.metric_variable_value > 0 AND da.date BETWEEN :start AND :end 
                            THEN da.total_consumption / m.metric_variable_value 
                            ELSE 0 END) AS total_per_metric,
                        COUNT(DISTINCT CASE WHEN m.metric_variable_value > 0 THEN m.id END) AS meters_with_metric
                    FROM sites s
                    LEFT JOIN meters m ON s.id = m.site_id AND m.is_active = 1
                    LEFT JOIN daily_aggregations da ON m.id = da.meter_id
                    WHERE s.is_active = 1' . $siteFilter . '
                    GROUP BY s.id, s.name
                    ORDER BY ' . ($showPerMetric ? 'total_per_metric DESC, ' : '') . 'total_consumption DESC, s.name ASC
                ');
                $stmt->execute($params);

                $rows = $stmt->fetchAll() ?: [];

                $total = 0.0;
                $totalPerMetric = 0.0;
                $hasMetricData = false;
                
                foreach ($rows as &$row) {
                    $row['total_consumption'] = (float) $row['total_consumption'];
                    $row['total_per_metric'] = (float) $row['total_per_metric'];
                    $row['meter_count'] = (int) $row['meter_count'];
                    $row['meters_with_metric'] = (int) $row['meters_with_metric'];
                    $total += $row['total_consumption'];
                    $totalPerMetric += $row['total_per_metric'];
                    
                    if ($row['meters_with_metric'] > 0) {
                        $hasMetricData = true;
                    }
                }

                $reportData['rows'] = $rows;
                $reportData['totalConsumption'] = $total;
                $reportData['totalPerMetric'] = $totalPerMetric;
                $reportData['hasMetricData'] = $hasMetricData;
            } catch (\Throwable $e) {
                $reportData['error'] = 'Unable to load consumption data right now.';
            }
        } else {
            $reportData['error'] = 'Database connection not available.';
        }

        return $this->view->render($response, 'reports/consumption.twig', [
            'page_title' => 'Energy Consumption Report',
            'period' => $period,
            'report' => $reportData,
            'showPerMetric' => $showPerMetric,
        ]);
    }

    public function costs(Request $request, Response $response): Response
    {
        $period = $this->resolvePeriod($request, 7);
        $reportData = [
            'rows' => [],
            'totalConsumption' => 0.0,
            'totalCost' => 0.0,
        ];

        if ($this->pdo) {
            try {
                $stmt = $this->pdo->prepare('
                    SELECT
                        COALESCE(sup.name, "Unknown Supplier") AS supplier_name,
                        SUM(da.total_consumption) AS total_consumption,
                        AVG(COALESCE(t.unit_rate, 0)) AS avg_unit_rate,
                        SUM(da.total_consumption * COALESCE(t.unit_rate, 0)) AS estimated_cost
                    FROM daily_aggregations da
                    JOIN meters m ON da.meter_id = m.id
                    LEFT JOIN suppliers sup ON m.supplier_id = sup.id
                    LEFT JOIN tariffs t ON t.supplier_id = m.supplier_id
                        AND t.unit_rate IS NOT NULL
                        AND (t.valid_from IS NULL OR t.valid_from <= da.date)
                        AND (t.valid_to IS NULL OR t.valid_to >= da.date)
                    WHERE da.date BETWEEN :start AND :end
                    GROUP BY sup.id, sup.name
                    ORDER BY estimated_cost DESC
                ');
                $stmt->execute([
                    'start' => $period['start']->format('Y-m-d'),
                    'end' => $period['end']->format('Y-m-d'),
                ]);

                $rows = $stmt->fetchAll() ?: [];

                $totalConsumption = 0.0;
                $totalCost = 0.0;
                foreach ($rows as &$row) {
                    $row['total_consumption'] = (float) $row['total_consumption'];
                    $row['avg_unit_rate'] = $row['avg_unit_rate'] !== null ? (float) $row['avg_unit_rate'] : null;
                    $row['estimated_cost'] = (float) $row['estimated_cost'];
                    $totalConsumption += $row['total_consumption'];
                    $totalCost += $row['estimated_cost'];
                }

                $reportData['rows'] = $rows;
                $reportData['totalConsumption'] = $totalConsumption;
                $reportData['totalCost'] = $totalCost;
            } catch (\Throwable $e) {
                $reportData['error'] = 'Unable to load cost analysis at the moment.';
            }
        } else {
            $reportData['error'] = 'Database connection not available.';
        }

        return $this->view->render($response, 'reports/costs.twig', [
            'page_title' => 'Cost Analysis Report',
            'period' => $period,
            'report' => $reportData,
        ]);
    }

    /**
     * Show actual vs estimated data quality report
     */
    public function dataQuality(Request $request, Response $response): Response
    {
        $period = $this->resolvePeriod($request, 7);
        $reportData = [
            'rows' => [],
            'totalActual' => 0.0,
            'totalEstimated' => 0.0,
            'actualPct' => 0,
        ];

        if ($this->pdo) {
            try {
                // Get actual vs estimated breakdown by date
                $stmt = $this->pdo->prepare('
                    SELECT 
                        reading_date,
                        reading_type,
                        SUM(reading_value) as total_kwh
                    FROM meter_readings
                    WHERE reading_date BETWEEN :start AND :end
                    GROUP BY reading_date, reading_type
                    ORDER BY reading_date ASC, reading_type ASC
                ');
                $stmt->execute([
                    'start' => $period['start']->format('Y-m-d'),
                    'end' => $period['end']->format('Y-m-d'),
                ]);

                $rawData = $stmt->fetchAll() ?: [];
                
                // Organize data by date
                $dateMap = [];
                foreach ($rawData as $row) {
                    $date = $row['reading_date'];
                    if (!isset($dateMap[$date])) {
                        $dateMap[$date] = [
                            'date' => $date,
                            'actual' => 0.0,
                            'estimated' => 0.0,
                            'total' => 0.0,
                        ];
                    }
                    
                    $value = (float) $row['total_kwh'];
                    if ($row['reading_type'] === 'actual') {
                        $dateMap[$date]['actual'] = $value;
                    } else {
                        $dateMap[$date]['estimated'] = $value;
                    }
                    $dateMap[$date]['total'] += $value;
                }

                $totalActual = 0.0;
                $totalEstimated = 0.0;
                
                foreach ($dateMap as &$row) {
                    if ($row['total'] > 0) {
                        $row['actual_pct'] = round(($row['actual'] / $row['total']) * 100);
                    } else {
                        $row['actual_pct'] = 0;
                    }
                    $totalActual += $row['actual'];
                    $totalEstimated += $row['estimated'];
                }

                $reportData['rows'] = array_values($dateMap);
                $reportData['totalActual'] = $totalActual;
                $reportData['totalEstimated'] = $totalEstimated;
                $total = $totalActual + $totalEstimated;
                
                if ($total > 0) {
                    $reportData['actualPct'] = round(($totalActual / $total) * 100);
                }
            } catch (\Throwable $e) {
                $reportData['error'] = 'Unable to load data quality report: ' . $e->getMessage();
            }
        } else {
            $reportData['error'] = 'Database connection not available.';
        }

        return $this->view->render($response, 'reports/data_quality.twig', [
            'page_title' => 'Data Quality Report',
            'period' => $period,
            'report' => $reportData,
        ]);
    }

    private function resolvePeriod(Request $request, int $days): array
    {
        $query = $request->getQueryParams();
        $end = isset($query['end']) ? DateTimeImmutable::createFromFormat('Y-m-d', $query['end']) ?: new DateTimeImmutable('today') : new DateTimeImmutable('today');

        if (isset($query['start'])) {
            $start = DateTimeImmutable::createFromFormat('Y-m-d', $query['start']);
        }

        if (empty($start)) {
            $start = $end->sub(new DateInterval('P' . max($days - 1, 1) . 'D'));
        }

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }
}
