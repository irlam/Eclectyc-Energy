<?php
/**
 * eclectyc-energy/app/Http/Controllers/Admin/MetersController.php
 * Admin UI controller for managing meters.
 */

namespace App\Http\Controllers\Admin;

use DateTimeImmutable;
use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class MetersController
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
        $meters = [];
        $totals = [
            'count' => 0,
            'active' => 0,
            'hh' => 0,
        ];

        if ($this->pdo) {
            try {
                $stmt = $this->pdo->query('
                    SELECT
                        m.id,
                        m.mpan,
                        m.serial_number,
                        m.meter_type,
                        m.is_half_hourly,
                        m.is_smart_meter,
                        m.is_active,
                        m.created_at,
                        s.name AS site_name,
                        sup.name AS supplier_name
                    FROM meters m
                    LEFT JOIN sites s ON s.id = m.site_id
                    LEFT JOIN suppliers sup ON sup.id = m.supplier_id
                    ORDER BY s.name ASC, m.mpan ASC
                ');
                $meters = $stmt->fetchAll() ?: [];

                foreach ($meters as &$meter) {
                    $meter['is_active'] = (bool) $meter['is_active'];
                    $meter['is_half_hourly'] = (bool) $meter['is_half_hourly'];
                    $meter['is_smart_meter'] = (bool) $meter['is_smart_meter'];
                    $totals['count']++;
                    if ($meter['is_active']) {
                        $totals['active']++;
                    }
                    if ($meter['is_half_hourly']) {
                        $totals['hh']++;
                    }
                }
            } catch (PDOException $e) {
                $meters = [];
            }
        }

        $flash = $_SESSION['meter_flash'] ?? null;
        unset($_SESSION['meter_flash']);

        return $this->view->render($response, 'admin/meters.twig', [
            'page_title' => 'Meters Management',
            'meters' => $meters,
            'totals' => $totals,
            'flash' => $flash,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/meters');
        }

        $sites = $this->pdo->query('SELECT id, name FROM sites ORDER BY name ASC')->fetchAll() ?: [];
        $suppliers = $this->pdo->query('SELECT id, name FROM suppliers ORDER BY name ASC')->fetchAll() ?: [];
        $flash = $_SESSION['meter_flash'] ?? null;
        unset($_SESSION['meter_flash']);

        return $this->view->render($response, 'admin/meters_create.twig', [
            'page_title' => 'Add Meter',
            'sites' => $sites,
            'suppliers' => $suppliers,
            'meter_types' => $this->getMeterTypes(),
            'flash' => $flash,
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/meters');
        }

        $data = $request->getParsedBody() ?? [];
        $errors = $this->validate($data);

        if (!empty($errors)) {
            $this->setFlash('error', 'Validation failed: ' . implode(', ', $errors));
            return $this->redirect($response, '/admin/meters/create');
        }

        $siteId = (int) $data['site_id'];
        $supplierId = !empty($data['supplier_id']) ? (int) $data['supplier_id'] : null;
        $mpan = strtoupper(trim($data['mpan']));
        $serial = trim($data['serial_number'] ?? '');
        $meterType = $data['meter_type'] ?? 'electricity';
        $isSmart = isset($data['is_smart_meter']) ? 1 : 0;
        $isHalfHourly = isset($data['is_half_hourly']) ? 1 : 0;
        $installationDate = !empty($data['installation_date']) ? $data['installation_date'] : null;
        $isActive = isset($data['is_active']) ? 1 : 0;

        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO meters
                    (site_id, supplier_id, mpan, serial_number, meter_type, is_smart_meter, is_half_hourly, installation_date, is_active)
                VALUES
                    (:site_id, :supplier_id, :mpan, :serial_number, :meter_type, :is_smart_meter, :is_half_hourly, :installation_date, :is_active)
                ON DUPLICATE KEY UPDATE
                    serial_number = VALUES(serial_number),
                    supplier_id = VALUES(supplier_id),
                    meter_type = VALUES(meter_type),
                    is_smart_meter = VALUES(is_smart_meter),
                    is_half_hourly = VALUES(is_half_hourly),
                    installation_date = VALUES(installation_date),
                    is_active = VALUES(is_active)
            ');
            $stmt->execute([
                'site_id' => $siteId,
                'supplier_id' => $supplierId,
                'mpan' => $mpan,
                'serial_number' => $serial !== '' ? $serial : null,
                'meter_type' => $meterType,
                'is_smart_meter' => $isSmart,
                'is_half_hourly' => $isHalfHourly,
                'installation_date' => $installationDate ?: null,
                'is_active' => $isActive,
            ]);

            $this->setFlash('success', 'Meter saved successfully.');
        } catch (PDOException $e) {
            $code = (int) $e->getCode();
            if ($code === 23000) {
                $this->setFlash('error', 'A meter with that MPAN already exists.');
            } else {
                $this->setFlash('error', 'Failed to save meter: ' . $e->getMessage());
            }
        }

        return $this->redirect($response, '/admin/meters');
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/meters');
        }

        $meterId = (int) $args['id'];
        
        $stmt = $this->pdo->prepare('SELECT * FROM meters WHERE id = :id');
        $stmt->execute(['id' => $meterId]);
        $meter = $stmt->fetch();

        if (!$meter) {
            $this->setFlash('error', 'Meter not found.');
            return $this->redirect($response, '/admin/meters');
        }

        $sites = $this->pdo->query('SELECT id, name FROM sites ORDER BY name ASC')->fetchAll() ?: [];
        $suppliers = $this->pdo->query('SELECT id, name FROM suppliers ORDER BY name ASC')->fetchAll() ?: [];
        $flash = $_SESSION['meter_flash'] ?? null;
        unset($_SESSION['meter_flash']);

        return $this->view->render($response, 'admin/meters_edit.twig', [
            'page_title' => 'Edit Meter',
            'meter' => $meter,
            'sites' => $sites,
            'suppliers' => $suppliers,
            'meter_types' => $this->getMeterTypes(),
            'flash' => $flash,
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/meters');
        }

        $meterId = (int) $args['id'];
        $data = $request->getParsedBody() ?? [];
        $errors = $this->validate($data, $meterId);

        if (!empty($errors)) {
            $this->setFlash('error', 'Validation failed: ' . implode(', ', $errors));
            return $this->redirect($response, '/admin/meters/' . $meterId . '/edit');
        }

        $siteId = (int) $data['site_id'];
        $supplierId = !empty($data['supplier_id']) ? (int) $data['supplier_id'] : null;
        $mpan = strtoupper(trim($data['mpan']));
        $serial = trim($data['serial_number'] ?? '');
        $meterType = $data['meter_type'] ?? 'electricity';
        $isSmart = isset($data['is_smart_meter']) ? 1 : 0;
        $isHalfHourly = isset($data['is_half_hourly']) ? 1 : 0;
        $installationDate = !empty($data['installation_date']) ? $data['installation_date'] : null;
        $isActive = isset($data['is_active']) ? 1 : 0;

        try {
            $stmt = $this->pdo->prepare('
                UPDATE meters
                SET site_id = :site_id,
                    supplier_id = :supplier_id,
                    mpan = :mpan,
                    serial_number = :serial_number,
                    meter_type = :meter_type,
                    is_smart_meter = :is_smart_meter,
                    is_half_hourly = :is_half_hourly,
                    installation_date = :installation_date,
                    is_active = :is_active
                WHERE id = :id
            ');
            $stmt->execute([
                'id' => $meterId,
                'site_id' => $siteId,
                'supplier_id' => $supplierId,
                'mpan' => $mpan,
                'serial_number' => $serial !== '' ? $serial : null,
                'meter_type' => $meterType,
                'is_smart_meter' => $isSmart,
                'is_half_hourly' => $isHalfHourly,
                'installation_date' => $installationDate ?: null,
                'is_active' => $isActive,
            ]);

            $this->setFlash('success', 'Meter updated successfully.');
        } catch (PDOException $e) {
            $code = (int) $e->getCode();
            if ($code === 23000) {
                $this->setFlash('error', 'A meter with that MPAN already exists.');
            } else {
                $this->setFlash('error', 'Failed to update meter: ' . $e->getMessage());
            }
        }

        return $this->redirect($response, '/admin/meters');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/meters');
        }

        $meterId = (int) $args['id'];

        try {
            // Check if meter has readings
            $stmt = $this->pdo->prepare('SELECT COUNT(*) as count FROM meter_readings WHERE meter_id = :id');
            $stmt->execute(['id' => $meterId]);
            $readingCount = $stmt->fetch()['count'] ?? 0;

            if ($readingCount > 0) {
                $this->setFlash('error', 'Cannot delete meter with associated readings. Archive it by marking as inactive instead.');
                return $this->redirect($response, '/admin/meters');
            }

            $stmt = $this->pdo->prepare('DELETE FROM meters WHERE id = :id');
            $stmt->execute(['id' => $meterId]);

            $this->setFlash('success', 'Meter deleted successfully.');
        } catch (PDOException $e) {
            $this->setFlash('error', 'Failed to delete meter: ' . $e->getMessage());
        }

        return $this->redirect($response, '/admin/meters');
    }

    private function validate(array $data, ?int $meterId = null): array
    {
        $errors = [];

        if (empty($data['site_id']) || !is_numeric($data['site_id'])) {
            $errors[] = 'Valid site is required';
        }

        $mpan = strtoupper(trim($data['mpan'] ?? ''));
        if ($mpan === '') {
            $errors[] = 'MPAN is required';
        } elseif (strlen($mpan) < 8) {
            $errors[] = 'MPAN must be at least 8 characters';
        } else {
            // Check MPAN uniqueness (excluding current meter during edit)
            if ($this->pdo) {
                $sql = 'SELECT COUNT(*) as count FROM meters WHERE mpan = :mpan';
                if ($meterId !== null) {
                    $sql .= ' AND id != :id';
                }
                $stmt = $this->pdo->prepare($sql);
                $params = ['mpan' => $mpan];
                if ($meterId !== null) {
                    $params['id'] = $meterId;
                }
                $stmt->execute($params);
                $count = $stmt->fetch()['count'] ?? 0;
                if ($count > 0) {
                    $errors[] = 'A meter with this MPAN already exists';
                }
            }
        }

        $types = array_keys($this->getMeterTypes());
        $meterType = $data['meter_type'] ?? 'electricity';
        if (!in_array($meterType, $types, true)) {
            $errors[] = 'Invalid meter type';
        }

        if (!empty($data['installation_date'])) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $data['installation_date']);
            if (!$parsed) {
                $errors[] = 'Installation date must be in YYYY-MM-DD format';
            }
        }

        if (!empty($data['supplier_id']) && !is_numeric($data['supplier_id'])) {
            $errors[] = 'Supplier selection is invalid';
        }

        return $errors;
    }

    private function getMeterTypes(): array
    {
        return [
            'electricity' => 'Electricity',
            'gas' => 'Gas',
            'water' => 'Water',
            'heat' => 'Heat',
        ];
    }

    private function setFlash(string $type, string $message): void
    {
        $_SESSION['meter_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    private function redirect(Response $response, string $path): Response
    {
        return $response->withHeader('Location', $path)->withStatus(302);
    }
}
