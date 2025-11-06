<?php
/**
 * eclectyc-energy/app/Http/Controllers/Admin/TariffsController.php
 * Lists tariffs for administrative management.
 */

namespace App\Http\Controllers\Admin;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class TariffsController
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
        $tariffs = [];

        if ($this->pdo) {
            try {
                $stmt = $this->pdo->query('
                    SELECT
                        t.id,
                        t.name,
                        t.code,
                        t.energy_type,
                        t.tariff_type,
                        t.unit_rate,
                        t.standing_charge,
                        t.valid_from,
                        t.valid_to,
                        t.peak_rate,
                        t.off_peak_rate,
                        t.weekend_rate,
                        t.is_active,
                        t.created_at,
                        COALESCE(sup.name, "Unknown") AS supplier_name
                    FROM tariffs t
                    LEFT JOIN suppliers sup ON t.supplier_id = sup.id
                    ORDER BY t.valid_from DESC, t.name ASC
                ');
                $tariffs = $stmt->fetchAll() ?: [];

                foreach ($tariffs as &$tariff) {
                    $tariff['unit_rate'] = $tariff['unit_rate'] !== null ? (float) $tariff['unit_rate'] : null;
                    $tariff['standing_charge'] = $tariff['standing_charge'] !== null ? (float) $tariff['standing_charge'] : null;
                    $tariff['peak_rate'] = $tariff['peak_rate'] !== null ? (float) $tariff['peak_rate'] : null;
                    $tariff['off_peak_rate'] = $tariff['off_peak_rate'] !== null ? (float) $tariff['off_peak_rate'] : null;
                    $tariff['weekend_rate'] = $tariff['weekend_rate'] !== null ? (float) $tariff['weekend_rate'] : null;
                    $tariff['is_active'] = (bool) $tariff['is_active'];
                }
            } catch (\Throwable $e) {
                $tariffs = [];
            }
        }

        return $this->view->render($response, 'admin/tariffs.twig', [
            'page_title' => 'Tariff Management',
            'tariffs' => $tariffs,
        ]);
    }
}
