<?php
/**
 * eclectyc-energy/app/Http/Controllers/Admin/SitesController.php
 * Lists company sites for admin management.
 */

namespace App\Http\Controllers\Admin;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class SitesController
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
        $sites = [];
        $totalMeters = 0;

        if ($this->pdo) {
            try {
                $stmt = $this->pdo->query('
                    SELECT
                        s.id,
                        s.name,
                        s.site_type,
                        s.postcode,
                        s.is_active,
                        s.created_at,
                        c.name AS company_name,
                        r.name AS region_name,
                        COUNT(m.id) AS meter_count
                    FROM sites s
                    LEFT JOIN companies c ON s.company_id = c.id
                    LEFT JOIN regions r ON s.region_id = r.id
                    LEFT JOIN meters m ON m.site_id = s.id
                    GROUP BY s.id, s.name, s.site_type, s.postcode, s.is_active, s.created_at, c.name, r.name
                    ORDER BY s.name ASC
                ');
                $sites = $stmt->fetchAll() ?: [];

                foreach ($sites as &$site) {
                    $site['meter_count'] = (int) $site['meter_count'];
                    $site['is_active'] = (bool) $site['is_active'];
                    $totalMeters += $site['meter_count'];
                }
            } catch (\Throwable $e) {
                $sites = [];
            }
        }

        return $this->view->render($response, 'admin/sites.twig', [
            'page_title' => 'Sites Management',
            'sites' => $sites,
            'totals' => [
                'site_count' => count($sites),
                'meter_count' => $totalMeters,
            ],
        ]);
    }
}
