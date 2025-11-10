<?php
/**
 * eclectyc-energy/app/Http/Controllers/Admin/SitesController.php
 * Full CRUD controller for site management in admin area.
 * Last updated: 06/11/2025
 */

namespace App\Http\Controllers\Admin;

use App\Models\Company;
use App\Models\Region;
use App\Models\Site;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class SitesController
{
    private const UK_POSTCODE_PATTERN = '/^[A-Z]{1,2}\d{1,2}[A-Z]?\s?\d[A-Z]{2}$/i';
    
    private Twig $view;
    private ?PDO $pdo;

    public function __construct(Twig $view, ?PDO $pdo)
    {
        $this->view = $view;
        $this->pdo = $pdo;
    }

    /**
     * List all sites
     */
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
        
        $sites = [];
        $totalMeters = 0;

        if ($this->pdo) {
            try {
                // Build WHERE clause for site filtering
                $siteFilter = '';
                if (!empty($accessibleSiteIds)) {
                    $placeholders = implode(',', array_fill(0, count($accessibleSiteIds), '?'));
                    $siteFilter = " WHERE s.id IN ($placeholders)";
                }
                
                $stmt = $this->pdo->prepare('
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
                    LEFT JOIN meters m ON m.site_id = s.id' . 
                    $siteFilter . '
                    GROUP BY s.id, s.name, s.site_type, s.postcode, s.is_active, s.created_at, c.name, r.name
                    ORDER BY s.name ASC
                ');
                
                if (!empty($accessibleSiteIds)) {
                    $stmt->execute($accessibleSiteIds);
                } else {
                    $stmt->execute();
                }
                
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

        $flash = $_SESSION['site_flash'] ?? null;
        unset($_SESSION['site_flash']);

        return $this->view->render($response, 'admin/sites.twig', [
            'page_title' => 'Sites Management',
            'sites' => $sites,
            'totals' => [
                'site_count' => count($sites),
                'meter_count' => $totalMeters,
            ],
            'flash' => $flash,
        ]);
    }

    /**
     * Show create form
     */
    public function create(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/sites');
        }

        // Fetch companies and regions for dropdowns
        $companies = $this->pdo->query('SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name')->fetchAll();
        $regions = $this->pdo->query('SELECT id, name FROM regions ORDER BY name')->fetchAll();

        return $this->view->render($response, 'admin/sites_create.twig', [
            'page_title' => 'Create New Site',
            'companies' => $companies,
            'regions' => $regions,
            'site_types' => $this->getSiteTypes(),
        ]);
    }

    /**
     * Store a new site
     */
    public function store(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/sites');
        }

        $data = $request->getParsedBody() ?? [];
        $errors = $this->validateSite($data);

        if (!empty($errors)) {
            $this->setFlash('error', 'Validation failed: ' . implode(', ', $errors));
            return $this->redirect($response, '/admin/sites/create');
        }

        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO sites (company_id, region_id, name, address, postcode, latitude, longitude, site_type, floor_area, is_active)
                VALUES (:company_id, :region_id, :name, :address, :postcode, :latitude, :longitude, :site_type, :floor_area, :is_active)
            ');

            $stmt->execute([
                'company_id' => (int) $data['company_id'],
                'region_id' => !empty($data['region_id']) ? (int) $data['region_id'] : null,
                'name' => trim($data['name']),
                'address' => trim($data['address']),
                'postcode' => trim($data['postcode'] ?? ''),
                'latitude' => !empty($data['latitude']) ? (float) $data['latitude'] : null,
                'longitude' => !empty($data['longitude']) ? (float) $data['longitude'] : null,
                'site_type' => $data['site_type'] ?? 'other',
                'floor_area' => !empty($data['floor_area']) ? (float) $data['floor_area'] : null,
                'is_active' => isset($data['is_active']) ? 1 : 0,
            ]);

            $this->setFlash('success', 'Site created successfully.');
        } catch (\PDOException $e) {
            $this->setFlash('error', 'Failed to create site: ' . $e->getMessage());
        }

        return $this->redirect($response, '/admin/sites');
    }

    /**
     * Show edit form
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/sites');
        }

        $siteId = (int) $args['id'];
        
        $stmt = $this->pdo->prepare('SELECT * FROM sites WHERE id = :id');
        $stmt->execute(['id' => $siteId]);
        $site = $stmt->fetch();

        if (!$site) {
            $this->setFlash('error', 'Site not found.');
            return $this->redirect($response, '/admin/sites');
        }

        // Fetch companies and regions for dropdowns
        $companies = $this->pdo->query('SELECT id, name FROM companies WHERE is_active = 1 ORDER BY name')->fetchAll();
        $regions = $this->pdo->query('SELECT id, name FROM regions ORDER BY name')->fetchAll();

        return $this->view->render($response, 'admin/sites_edit.twig', [
            'page_title' => 'Edit Site',
            'site' => $site,
            'companies' => $companies,
            'regions' => $regions,
            'site_types' => $this->getSiteTypes(),
        ]);
    }

    /**
     * Update an existing site
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/sites');
        }

        $siteId = (int) $args['id'];
        $data = $request->getParsedBody() ?? [];
        $errors = $this->validateSite($data, $siteId);

        if (!empty($errors)) {
            $this->setFlash('error', 'Validation failed: ' . implode(', ', $errors));
            return $this->redirect($response, '/admin/sites/' . $siteId . '/edit');
        }

        try {
            $stmt = $this->pdo->prepare('
                UPDATE sites
                SET company_id = :company_id,
                    region_id = :region_id,
                    name = :name,
                    address = :address,
                    postcode = :postcode,
                    latitude = :latitude,
                    longitude = :longitude,
                    site_type = :site_type,
                    floor_area = :floor_area,
                    is_active = :is_active
                WHERE id = :id
            ');

            $stmt->execute([
                'id' => $siteId,
                'company_id' => (int) $data['company_id'],
                'region_id' => !empty($data['region_id']) ? (int) $data['region_id'] : null,
                'name' => trim($data['name']),
                'address' => trim($data['address']),
                'postcode' => trim($data['postcode'] ?? ''),
                'latitude' => !empty($data['latitude']) ? (float) $data['latitude'] : null,
                'longitude' => !empty($data['longitude']) ? (float) $data['longitude'] : null,
                'site_type' => $data['site_type'] ?? 'other',
                'floor_area' => !empty($data['floor_area']) ? (float) $data['floor_area'] : null,
                'is_active' => isset($data['is_active']) ? 1 : 0,
            ]);

            $this->setFlash('success', 'Site updated successfully.');
        } catch (\PDOException $e) {
            $this->setFlash('error', 'Failed to update site: ' . $e->getMessage());
        }

        return $this->redirect($response, '/admin/sites');
    }

    /**
     * Delete a site
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/sites');
        }

        $siteId = (int) $args['id'];

        try {
            // Check if site has meters
            $stmt = $this->pdo->prepare('SELECT COUNT(*) as count FROM meters WHERE site_id = :id');
            $stmt->execute(['id' => $siteId]);
            $meterCount = $stmt->fetch()['count'] ?? 0;

            if ($meterCount > 0) {
                $this->setFlash('error', 'Cannot delete site with associated meters. Remove or reassign meters first.');
                return $this->redirect($response, '/admin/sites');
            }

            $stmt = $this->pdo->prepare('DELETE FROM sites WHERE id = :id');
            $stmt->execute(['id' => $siteId]);

            $this->setFlash('success', 'Site deleted successfully.');
        } catch (\PDOException $e) {
            $this->setFlash('error', 'Failed to delete site: ' . $e->getMessage());
        }

        return $this->redirect($response, '/admin/sites');
    }

    /**
     * Validate site data
     */
    private function validateSite(array $data, ?int $siteId = null): array
    {
        $errors = [];

        if (empty($data['name']) || strlen(trim($data['name'])) < 2) {
            $errors[] = 'Site name is required (minimum 2 characters)';
        }

        if (empty($data['company_id']) || !is_numeric($data['company_id'])) {
            $errors[] = 'Valid company is required';
        }

        if (empty($data['address']) || strlen(trim($data['address'])) < 5) {
            $errors[] = 'Address is required (minimum 5 characters)';
        }

        if (!empty($data['postcode'])) {
            $postcode = strtoupper(trim($data['postcode']));
            if (!preg_match(self::UK_POSTCODE_PATTERN, $postcode)) {
                $errors[] = 'Invalid UK postcode format';
            }
        }

        if (!empty($data['latitude'])) {
            $lat = (float) $data['latitude'];
            if ($lat < -90 || $lat > 90) {
                $errors[] = 'Latitude must be between -90 and 90';
            }
        }

        if (!empty($data['longitude'])) {
            $lng = (float) $data['longitude'];
            if ($lng < -180 || $lng > 180) {
                $errors[] = 'Longitude must be between -180 and 180';
            }
        }

        if (!empty($data['floor_area'])) {
            $area = (float) $data['floor_area'];
            if ($area <= 0) {
                $errors[] = 'Floor area must be greater than 0';
            }
        }

        $validTypes = array_keys($this->getSiteTypes());
        if (!empty($data['site_type']) && !in_array($data['site_type'], $validTypes, true)) {
            $errors[] = 'Invalid site type';
        }

        return $errors;
    }

    /**
     * Get available site types
     */
    private function getSiteTypes(): array
    {
        return [
            'office' => 'Office',
            'warehouse' => 'Warehouse',
            'retail' => 'Retail',
            'industrial' => 'Industrial',
            'residential' => 'Residential',
            'other' => 'Other',
        ];
    }

    /**
     * Set flash message
     */
    private function setFlash(string $type, string $message): void
    {
        $_SESSION['site_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * Redirect helper
     */
    private function redirect(Response $response, string $path): Response
    {
        return $response->withHeader('Location', $path)->withStatus(302);
    }
}
