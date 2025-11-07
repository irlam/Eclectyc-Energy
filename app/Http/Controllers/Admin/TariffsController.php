<?php
/**
 * eclectyc-energy/app/Http/Controllers/Admin/TariffsController.php
 * Lists tariffs for administrative management.
 */

namespace App\Http\Controllers\Admin;

use PDO;
use PDOException;
use DateTimeImmutable;
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

        $flash = $_SESSION['tariff_flash'] ?? null;
        unset($_SESSION['tariff_flash']);

        return $this->view->render($response, 'admin/tariffs.twig', [
            'page_title' => 'Tariff Management',
            'tariffs' => $tariffs,
            'flash' => $flash,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/tariffs');
        }

        $suppliers = $this->pdo->query('SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name')->fetchAll();
        $flash = $_SESSION['tariff_flash'] ?? null;
        unset($_SESSION['tariff_flash']);

        return $this->view->render($response, 'admin/tariffs_create.twig', [
            'page_title' => 'Create New Tariff',
            'suppliers' => $suppliers,
            'energy_types' => $this->getEnergyTypes(),
            'tariff_types' => $this->getTariffTypes(),
            'flash' => $flash,
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/tariffs');
        }

        $data = $request->getParsedBody() ?? [];
        $errors = $this->validate($data);

        if (!empty($errors)) {
            $this->setFlash('error', 'Validation failed: ' . implode(', ', $errors));
            return $this->redirect($response, '/admin/tariffs/create');
        }

        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO tariffs (
                    supplier_id, name, code, energy_type, tariff_type,
                    unit_rate, standing_charge, valid_from, valid_to,
                    peak_rate, off_peak_rate, weekend_rate, is_active
                )
                VALUES (
                    :supplier_id, :name, :code, :energy_type, :tariff_type,
                    :unit_rate, :standing_charge, :valid_from, :valid_to,
                    :peak_rate, :off_peak_rate, :weekend_rate, :is_active
                )
            ');

            $stmt->execute([
                'supplier_id' => !empty($data['supplier_id']) ? (int) $data['supplier_id'] : null,
                'name' => trim($data['name']),
                'code' => !empty($data['code']) ? trim($data['code']) : null,
                'energy_type' => $data['energy_type'],
                'tariff_type' => $data['tariff_type'],
                'unit_rate' => !empty($data['unit_rate']) ? (float) $data['unit_rate'] : null,
                'standing_charge' => !empty($data['standing_charge']) ? (float) $data['standing_charge'] : null,
                'valid_from' => $data['valid_from'],
                'valid_to' => !empty($data['valid_to']) ? $data['valid_to'] : null,
                'peak_rate' => !empty($data['peak_rate']) ? (float) $data['peak_rate'] : null,
                'off_peak_rate' => !empty($data['off_peak_rate']) ? (float) $data['off_peak_rate'] : null,
                'weekend_rate' => !empty($data['weekend_rate']) ? (float) $data['weekend_rate'] : null,
                'is_active' => isset($data['is_active']) ? 1 : 0,
            ]);

            $this->setFlash('success', 'Tariff created successfully.');
        } catch (PDOException $e) {
            $this->setFlash('error', 'Failed to create tariff: ' . $e->getMessage());
        }

        return $this->redirect($response, '/admin/tariffs');
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/tariffs');
        }

        $tariffId = (int) $args['id'];
        
        $stmt = $this->pdo->prepare('SELECT * FROM tariffs WHERE id = :id');
        $stmt->execute(['id' => $tariffId]);
        $tariff = $stmt->fetch();

        if (!$tariff) {
            $this->setFlash('error', 'Tariff not found.');
            return $this->redirect($response, '/admin/tariffs');
        }

        $suppliers = $this->pdo->query('SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name')->fetchAll();
        $flash = $_SESSION['tariff_flash'] ?? null;
        unset($_SESSION['tariff_flash']);

        return $this->view->render($response, 'admin/tariffs_edit.twig', [
            'page_title' => 'Edit Tariff',
            'tariff' => $tariff,
            'suppliers' => $suppliers,
            'energy_types' => $this->getEnergyTypes(),
            'tariff_types' => $this->getTariffTypes(),
            'flash' => $flash,
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/tariffs');
        }

        $tariffId = (int) $args['id'];
        $data = $request->getParsedBody() ?? [];
        $errors = $this->validate($data, $tariffId);

        if (!empty($errors)) {
            $this->setFlash('error', 'Validation failed: ' . implode(', ', $errors));
            return $this->redirect($response, '/admin/tariffs/' . $tariffId . '/edit');
        }

        try {
            $stmt = $this->pdo->prepare('
                UPDATE tariffs
                SET supplier_id = :supplier_id,
                    name = :name,
                    code = :code,
                    energy_type = :energy_type,
                    tariff_type = :tariff_type,
                    unit_rate = :unit_rate,
                    standing_charge = :standing_charge,
                    valid_from = :valid_from,
                    valid_to = :valid_to,
                    peak_rate = :peak_rate,
                    off_peak_rate = :off_peak_rate,
                    weekend_rate = :weekend_rate,
                    is_active = :is_active
                WHERE id = :id
            ');

            $stmt->execute([
                'id' => $tariffId,
                'supplier_id' => !empty($data['supplier_id']) ? (int) $data['supplier_id'] : null,
                'name' => trim($data['name']),
                'code' => !empty($data['code']) ? trim($data['code']) : null,
                'energy_type' => $data['energy_type'],
                'tariff_type' => $data['tariff_type'],
                'unit_rate' => !empty($data['unit_rate']) ? (float) $data['unit_rate'] : null,
                'standing_charge' => !empty($data['standing_charge']) ? (float) $data['standing_charge'] : null,
                'valid_from' => $data['valid_from'],
                'valid_to' => !empty($data['valid_to']) ? $data['valid_to'] : null,
                'peak_rate' => !empty($data['peak_rate']) ? (float) $data['peak_rate'] : null,
                'off_peak_rate' => !empty($data['off_peak_rate']) ? (float) $data['off_peak_rate'] : null,
                'weekend_rate' => !empty($data['weekend_rate']) ? (float) $data['weekend_rate'] : null,
                'is_active' => isset($data['is_active']) ? 1 : 0,
            ]);

            $this->setFlash('success', 'Tariff updated successfully.');
        } catch (PDOException $e) {
            $this->setFlash('error', 'Failed to update tariff: ' . $e->getMessage());
        }

        return $this->redirect($response, '/admin/tariffs');
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/tariffs');
        }

        $tariffId = (int) $args['id'];

        try {
            $stmt = $this->pdo->prepare('DELETE FROM tariffs WHERE id = :id');
            $stmt->execute(['id' => $tariffId]);

            $this->setFlash('success', 'Tariff deleted successfully.');
        } catch (PDOException $e) {
            $this->setFlash('error', 'Failed to delete tariff: ' . $e->getMessage());
        }

        return $this->redirect($response, '/admin/tariffs');
    }

    private function validate(array $data, ?int $tariffId = null): array
    {
        $errors = [];

        if (empty($data['name']) || strlen(trim($data['name'])) < 2) {
            $errors[] = 'Tariff name is required (minimum 2 characters)';
        }

        if (!empty($data['code'])) {
            // Check code uniqueness (excluding current tariff during edit)
            if ($this->pdo) {
                $sql = 'SELECT COUNT(*) as count FROM tariffs WHERE code = :code';
                if ($tariffId !== null) {
                    $sql .= ' AND id != :id';
                }
                $stmt = $this->pdo->prepare($sql);
                $params = ['code' => trim($data['code'])];
                if ($tariffId !== null) {
                    $params['id'] = $tariffId;
                }
                $stmt->execute($params);
                $count = $stmt->fetch()['count'] ?? 0;
                if ($count > 0) {
                    $errors[] = 'A tariff with this code already exists';
                }
            }
        }

        $validEnergyTypes = array_keys($this->getEnergyTypes());
        if (empty($data['energy_type']) || !in_array($data['energy_type'], $validEnergyTypes, true)) {
            $errors[] = 'Valid energy type is required';
        }

        $validTariffTypes = array_keys($this->getTariffTypes());
        if (empty($data['tariff_type']) || !in_array($data['tariff_type'], $validTariffTypes, true)) {
            $errors[] = 'Valid tariff type is required';
        }

        if (empty($data['valid_from'])) {
            $errors[] = 'Valid from date is required';
        } else {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $data['valid_from']);
            if (!$parsed) {
                $errors[] = 'Valid from date must be in YYYY-MM-DD format';
            }
        }

        if (!empty($data['valid_to'])) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $data['valid_to']);
            if (!$parsed) {
                $errors[] = 'Valid to date must be in YYYY-MM-DD format';
            } elseif (!empty($data['valid_from'])) {
                $validFrom = DateTimeImmutable::createFromFormat('Y-m-d', $data['valid_from']);
                if ($validFrom && $parsed < $validFrom) {
                    $errors[] = 'Valid to date must be after valid from date';
                }
            }
        }

        // Validate rates are positive numbers
        $rateFields = ['unit_rate', 'standing_charge', 'peak_rate', 'off_peak_rate', 'weekend_rate'];
        foreach ($rateFields as $field) {
            if (!empty($data[$field]) && (float) $data[$field] < 0) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' must be a positive number';
            }
        }

        return $errors;
    }

    private function getEnergyTypes(): array
    {
        return [
            'electricity' => 'Electricity',
            'gas' => 'Gas',
        ];
    }

    private function getTariffTypes(): array
    {
        return [
            'fixed' => 'Fixed Rate',
            'variable' => 'Variable Rate',
            'time_of_use' => 'Time of Use',
            'dynamic' => 'Dynamic Pricing',
        ];
    }

    private function setFlash(string $type, string $message): void
    {
        $_SESSION['tariff_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    private function redirect(Response $response, string $path): Response
    {
        return $response->withHeader('Location', $path)->withStatus(302);
    }
}
