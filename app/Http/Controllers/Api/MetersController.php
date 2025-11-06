<?php
/**
 * eclectyc-energy/app/Http/Controllers/Api/MetersController.php
 * Provides meter-related API responses.
 */

namespace App\Http\Controllers\Api;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MetersController
{
    private ?PDO $pdo;

    public function __construct(?PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function index(Request $request, Response $response): Response
    {
        $data = [];

        if ($this->pdo) {
            try {
                $stmt = $this->pdo->query('
                    SELECT m.*, s.name AS site_name
                    FROM meters m
                    LEFT JOIN sites s ON m.site_id = s.id
                    ORDER BY m.mpan
                ');
                $data = $stmt->fetchAll() ?: [];
            } catch (\Throwable $e) {
                // Ignore and fall through with empty data
            }
        }

        $payload = json_encode([
            'status' => 'success',
            'data' => $data,
        ]);

        $response->getBody()->write($payload ?: '{}');
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function readings(Request $request, Response $response, array $args): Response
    {
        $mpan = $args['mpan'] ?? null;
        $readings = [];
        $meter = null;

        if ($this->pdo && $mpan) {
            try {
                $meterStmt = $this->pdo->prepare('SELECT id, mpan, site_id FROM meters WHERE mpan = :mpan LIMIT 1');
                $meterStmt->execute(['mpan' => $mpan]);
                $meter = $meterStmt->fetch();

                if ($meter) {
                    $limit = (int) ($request->getQueryParams()['limit'] ?? 30);
                    $limit = max(1, min($limit, 365));

                    $readingStmt = $this->pdo->prepare('
                        SELECT date, total_consumption, peak_consumption, off_peak_consumption, reading_count
                        FROM daily_aggregations
                        WHERE meter_id = :meter_id
                        ORDER BY date DESC
                        LIMIT :limit
                    ');
                    $readingStmt->bindValue(':meter_id', $meter['id'], PDO::PARAM_INT);
                    $readingStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                    $readingStmt->execute();
                    $readings = $readingStmt->fetchAll() ?: [];
                }
            } catch (\Throwable $e) {
                $meter = null;
                $readings = [];
            }
        }

        $payload = json_encode([
            'status' => $meter ? 'success' : 'not_found',
            'mpan' => $mpan,
            'data' => $readings,
        ]);

        $response->getBody()->write($payload ?: '{}');
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($meter ? 200 : 404);
    }
}
