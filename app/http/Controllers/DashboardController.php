<?php
/**
 * eclectyc-energy/app/http/Controllers/DashboardController.php
 * Renders the main dashboard page
 */

namespace App\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class DashboardController
{
    private Twig $view;

    public function __construct(Twig $view)
    {
        $this->view = $view;
    }

    public function index(Request $request, Response $response): Response
    {
        $container = $this->view->getEnvironment()->getGlobals()['container'] ?? null;
        $db = $container ? $container->get('db') : null;

        $stats = [
            'total_sites' => 0,
            'total_meters' => 0,
            'total_readings' => 0,
            'last_import' => 'Never'
        ];

        if ($db) {
            try {
                $stmt = $db->query('SELECT COUNT(*) as count FROM sites');
                $stats['total_sites'] = (int) ($stmt->fetch()['count'] ?? 0);

                $stmt = $db->query('SELECT COUNT(*) as count FROM meters');
                $stats['total_meters'] = (int) ($stmt->fetch()['count'] ?? 0);

                $stmt = $db->query('SELECT COUNT(*) as count FROM meter_readings');
                $stats['total_readings'] = (int) ($stmt->fetch()['count'] ?? 0);

                $stmt = $db->query('SELECT MAX(created_at) as last FROM meter_readings');
                $last = $stmt->fetch()['last'] ?? null;
                if ($last) {
                    $stats['last_import'] = date('d/m/Y H:i:s', strtotime($last));
                }
            } catch (\Throwable $e) {
                // Ignore errors and use default stats
            }
        }

        return $this->view->render($response, 'dashboard.twig', [
            'page_title' => 'Dashboard',
            'stats' => $stats,
        ]);
    }
}
