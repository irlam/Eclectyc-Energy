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
        $period = $this->resolvePeriod($request, 7);
        $reportData = [
            'rows' => [],
            'totalConsumption' => 0.0,
        ];

        if ($this->pdo) {
            try {
                // Modified query to show ALL sites, including those with no data
                $stmt = $this->pdo->prepare('
                    SELECT
                        s.id AS site_id,
                        s.name AS site_name,
                        COUNT(DISTINCT CASE WHEN da.date BETWEEN :start AND :end THEN m.id END) AS meter_count,
                        COALESCE(SUM(CASE WHEN da.date BETWEEN :start AND :end THEN da.total_consumption END), 0) AS total_consumption,
                        MIN(CASE WHEN da.date BETWEEN :start AND :end THEN da.date END) AS first_reading,
                        MAX(CASE WHEN da.date BETWEEN :start AND :end THEN da.date END) AS last_reading
                    FROM sites s
                    LEFT JOIN meters m ON s.id = m.site_id AND m.is_active = 1
                    LEFT JOIN daily_aggregations da ON m.id = da.meter_id
                    WHERE s.is_active = 1
                    GROUP BY s.id, s.name
                    ORDER BY total_consumption DESC, s.name ASC
                ');
                $stmt->execute([
                    'start' => $period['start']->format('Y-m-d'),
                    'end' => $period['end']->format('Y-m-d'),
                ]);

                $rows = $stmt->fetchAll() ?: [];

                $total = 0.0;
                foreach ($rows as &$row) {
                    $row['total_consumption'] = (float) $row['total_consumption'];
                    $row['meter_count'] = (int) $row['meter_count'];
                    $total += $row['total_consumption'];
                }

                $reportData['rows'] = $rows;
                $reportData['totalConsumption'] = $total;
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
