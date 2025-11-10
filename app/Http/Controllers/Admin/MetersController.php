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
        
        $meters = [];
        $totals = [
            'count' => 0,
            'active' => 0,
            'hh' => 0,
        ];

        // Pagination parameters
        $query = $request->getQueryParams();
        $page = isset($query['page']) ? max(1, (int) $query['page']) : 1;
        $perPage = isset($query['per_page']) ? max(1, min(100, (int) $query['per_page'])) : 10;
        $offset = ($page - 1) * $perPage;

        if ($this->pdo) {
            try {
                // Build WHERE clause for site filtering
                $siteFilter = '';
                if (!empty($accessibleSiteIds)) {
                    $placeholders = implode(',', array_fill(0, count($accessibleSiteIds), '?'));
                    $siteFilter = " WHERE m.site_id IN ($placeholders)";
                }
                
                // Get total counts first
                $countStmt = $this->pdo->prepare('
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN m.is_active = 1 THEN 1 ELSE 0 END) as active,
                        SUM(CASE WHEN m.is_half_hourly = 1 THEN 1 ELSE 0 END) as hh
                    FROM meters m' . $siteFilter
                );
                
                if (!empty($accessibleSiteIds)) {
                    $countStmt->execute($accessibleSiteIds);
                } else {
                    $countStmt->execute();
                }
                
                $counts = $countStmt->fetch();
                $totals = [
                    'count' => (int) $counts['total'],
                    'active' => (int) $counts['active'],
                    'hh' => (int) $counts['hh'],
                ];

                // Get paginated meters (ordered by most recently created first)
                $stmt = $this->pdo->prepare('
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
                    LEFT JOIN suppliers sup ON sup.id = m.supplier_id' .
                    $siteFilter . '
                    ORDER BY m.created_at DESC, m.id DESC
                    LIMIT :limit OFFSET :offset
                ');
                
                // Bind site IDs if filtering
                if (!empty($accessibleSiteIds)) {
                    $paramIndex = 1;
                    foreach ($accessibleSiteIds as $siteId) {
                        $stmt->bindValue($paramIndex++, $siteId, PDO::PARAM_INT);
                    }
                }
                
                $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $meters = $stmt->fetchAll() ?: [];

                foreach ($meters as &$meter) {
                    $meter['is_active'] = (bool) $meter['is_active'];
                    $meter['is_half_hourly'] = (bool) $meter['is_half_hourly'];
                    $meter['is_smart_meter'] = (bool) $meter['is_smart_meter'];
                }
            } catch (PDOException $e) {
                $meters = [];
            }
        }

        $flash = $_SESSION['meter_flash'] ?? null;
        unset($_SESSION['meter_flash']);

        // Calculate pagination info
        $totalPages = $totals['count'] > 0 ? (int) ceil($totals['count'] / $perPage) : 1;

        return $this->view->render($response, 'admin/meters.twig', [
            'page_title' => 'Meters Management',
            'meters' => $meters,
            'totals' => $totals,
            'flash' => $flash,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
                'total_items' => $totals['count'],
            ],
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
        $metricVariableName = trim($data['metric_variable_name'] ?? '');
        $metricVariableValue = !empty($data['metric_variable_value']) ? (float) $data['metric_variable_value'] : null;

        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO meters
                    (site_id, supplier_id, mpan, serial_number, meter_type, is_smart_meter, is_half_hourly, installation_date, is_active, metric_variable_name, metric_variable_value)
                VALUES
                    (:site_id, :supplier_id, :mpan, :serial_number, :meter_type, :is_smart_meter, :is_half_hourly, :installation_date, :is_active, :metric_variable_name, :metric_variable_value)
                ON DUPLICATE KEY UPDATE
                    serial_number = VALUES(serial_number),
                    supplier_id = VALUES(supplier_id),
                    meter_type = VALUES(meter_type),
                    is_smart_meter = VALUES(is_smart_meter),
                    is_half_hourly = VALUES(is_half_hourly),
                    installation_date = VALUES(installation_date),
                    is_active = VALUES(is_active),
                    metric_variable_name = VALUES(metric_variable_name),
                    metric_variable_value = VALUES(metric_variable_value)
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
                'metric_variable_name' => $metricVariableName !== '' ? $metricVariableName : null,
                'metric_variable_value' => $metricVariableValue,
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
        $metricVariableName = trim($data['metric_variable_name'] ?? '');
        $metricVariableValue = !empty($data['metric_variable_value']) ? (float) $data['metric_variable_value'] : null;

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
                    is_active = :is_active,
                    metric_variable_name = :metric_variable_name,
                    metric_variable_value = :metric_variable_value
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
                'metric_variable_name' => $metricVariableName !== '' ? $metricVariableName : null,
                'metric_variable_value' => $metricVariableValue,
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

    /**
     * Show carbon intensity data for a specific meter
     */
    public function carbonIntensity(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/meters');
        }

        $meterId = (int) $args['id'];
        
        // Get meter details
        $stmt = $this->pdo->prepare('
            SELECT m.*, s.name as site_name, sup.name as supplier_name
            FROM meters m
            LEFT JOIN sites s ON s.id = m.site_id
            LEFT JOIN suppliers sup ON sup.id = m.supplier_id
            WHERE m.id = :id
        ');
        $stmt->execute(['id' => $meterId]);
        $meter = $stmt->fetch();

        if (!$meter) {
            $this->setFlash('error', 'Meter not found.');
            return $this->redirect($response, '/admin/meters');
        }

        // Get query parameters for date range
        $query = $request->getQueryParams();
        $days = isset($query['days']) ? max(1, min(90, (int) $query['days'])) : 7;
        
        // Get recent consumption data with carbon intensity
        $stmt = $this->pdo->prepare('
            SELECT 
                da.date,
                da.total_consumption,
                AVG(eci.intensity) as avg_carbon_intensity,
                AVG(eci.forecast) as avg_forecast,
                AVG(eci.actual) as avg_actual
            FROM daily_aggregations da
            LEFT JOIN external_carbon_intensity eci 
                ON DATE(eci.datetime) = da.date
                AND eci.region = "GB"
            WHERE da.meter_id = :meter_id
                AND da.date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY da.date
            ORDER BY da.date DESC
        ');
        $stmt->execute(['meter_id' => $meterId, 'days' => $days]);
        $data = $stmt->fetchAll() ?: [];

        // Calculate emissions
        $totalConsumption = 0;
        $totalEmissions = 0;
        foreach ($data as &$row) {
            $row['total_consumption'] = (float) $row['total_consumption'];
            $row['avg_carbon_intensity'] = $row['avg_carbon_intensity'] ? (float) $row['avg_carbon_intensity'] : null;
            $row['avg_forecast'] = $row['avg_forecast'] ? (float) $row['avg_forecast'] : null;
            $row['avg_actual'] = $row['avg_actual'] ? (float) $row['avg_actual'] : null;
            
            $carbonIntensity = $row['avg_actual'] ?? $row['avg_carbon_intensity'] ?? 0;
            $row['emissions_kg'] = ($row['total_consumption'] * $carbonIntensity) / 1000;
            
            $totalConsumption += $row['total_consumption'];
            $totalEmissions += $row['emissions_kg'];
        }

        // Get latest carbon intensity
        $stmt = $this->pdo->query('
            SELECT intensity, forecast, actual, datetime
            FROM external_carbon_intensity
            WHERE region = "GB"
            ORDER BY datetime DESC
            LIMIT 1
        ');
        $latestCarbon = $stmt->fetch();

        return $this->view->render($response, 'admin/meters_carbon.twig', [
            'page_title' => 'Carbon Intensity - ' . $meter['mpan'],
            'meter' => $meter,
            'data' => $data,
            'days' => $days,
            'total_consumption' => $totalConsumption,
            'total_emissions' => $totalEmissions,
            'avg_emissions' => count($data) > 0 ? $totalEmissions / count($data) : 0,
            'latest_carbon' => $latestCarbon,
        ]);
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
