<?php
/**
 * eclectyc-energy/app/Http/Controllers/Api/ImportStatusController.php
 * Summaries for ingestion status endpoints.
 */

namespace App\Http\Controllers\Api;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ImportStatusController
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(Request $request, Response $response): Response
    {
        $summary = [
            'status' => 'ok',
            'total_daily_aggregations' => 0,
            'latest_aggregation' => null,
            'latest_reading' => null,
        ];

        if ($this->pdo) {
            try {
                $summary['total_daily_aggregations'] = (int) $this->pdo
                    ->query('SELECT COUNT(*) FROM daily_aggregations')
                    ->fetchColumn();

                $summary['latest_aggregation'] = $this->pdo
                    ->query('SELECT MAX(date) FROM daily_aggregations')
                    ->fetchColumn() ?: null;

                $summary['latest_reading'] = $this->pdo
                    ->query('SELECT MAX(reading_datetime) FROM meter_readings')
                    ->fetchColumn() ?: null;
            } catch (\Throwable $e) {
                $summary['status'] = 'error';
            }
        } else {
            $summary['status'] = 'error';
        }

        $payload = json_encode($summary);
        $response->getBody()->write($payload ?: '{}');

        return $response->withHeader('Content-Type', 'application/json');
    }
}
