<?php
/**
 * eclectyc-energy/app/Http/Controllers/Admin/ExportsController.php
 * Lists historical SFTP exports for admin visibility.
 */

namespace App\Http\Controllers\Admin;

use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class ExportsController
{
    private Twig $view;
    private ?PDO $pdo;

    public function __construct(Twig $view, ?PDO $pdo)
    {
        $this->view = $view;
        $this->pdo = $pdo;
    }

    public function index(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            return $this->view->render($response, 'admin/exports.twig', [
                'page_title' => 'Exports',
                'error' => 'Database connection unavailable.',
                'exports' => [],
                'filters' => [
                    'status' => null,
                    'type' => null,
                    'limit' => 50,
                ],
            ]);
        }

        $params = $request->getQueryParams();
        $statusFilter = isset($params['status']) && $params['status'] !== '' ? $params['status'] : null;
        $typeFilter = isset($params['type']) && $params['type'] !== '' ? $params['type'] : null;
        $limit = isset($params['limit']) ? max(10, min(200, (int) $params['limit'])) : 50;

        $criteria = [];
        $bindings = [];

        if ($statusFilter) {
            $criteria[] = 'e.status = :status';
            $bindings[':status'] = $statusFilter;
        }

        if ($typeFilter) {
            $criteria[] = 'e.export_type = :type';
            $bindings[':type'] = $typeFilter;
        }

        $whereClause = $criteria ? 'WHERE ' . implode(' AND ', $criteria) : '';

        $sql = "SELECT e.id, e.export_type, e.export_format, e.file_name, e.file_path, e.file_size, e.status, e.error_message, e.created_at, e.completed_at, u.name, u.email
            FROM exports e
            LEFT JOIN users u ON e.created_by = u.id
            $whereClause
            ORDER BY e.created_at DESC
            LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        foreach ($bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

        try {
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            return $this->view->render($response, 'admin/exports.twig', [
                'page_title' => 'Exports',
                'error' => 'Failed to load exports: ' . $exception->getMessage(),
                'exports' => [],
                'filters' => [
                    'status' => $statusFilter,
                    'type' => $typeFilter,
                    'limit' => $limit,
                ],
            ]);
        }

        $exports = array_map(static function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'export_type' => $row['export_type'],
                'export_format' => $row['export_format'],
                'file_name' => $row['file_name'],
                'file_path' => $row['file_path'],
                'file_size' => $row['file_size'],
                'status' => $row['status'],
                'error_message' => $row['error_message'],
                'created_at' => $row['created_at'],
                'completed_at' => $row['completed_at'],
                'user_name' => $row['name'] ?? null,
                'user_email' => $row['email'] ?? null,
            ];
        }, $rows);

        return $this->view->render($response, 'admin/exports.twig', [
            'page_title' => 'Exports',
            'error' => null,
            'exports' => $exports,
            'filters' => [
                'status' => $statusFilter,
                'type' => $typeFilter,
                'limit' => $limit,
            ],
        ]);
    }
}
