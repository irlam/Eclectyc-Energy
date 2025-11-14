<?php
/**
 * eclectyc-energy/app/Http/Controllers/Admin/AlarmsController.php
 * Controller for alarm management
 */

namespace App\Http\Controllers\Admin;

use App\Models\Alarm;
use App\Models\Site;
use App\Models\Meter;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class AlarmsController
{
    private Twig $view;
    private ?PDO $pdo;

    public function __construct(Twig $view, ?PDO $pdo)
    {
        $this->view = $view;
        $this->pdo = $pdo;
    }

    /**
     * List all alarms
     */
    public function index(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'] ?? null;
        $userId = $user['id'] ?? null;

        $alarms = [];
        if ($this->pdo && $userId) {
            try {
                // Get alarms for current user or all if admin
                $isAdmin = ($user['role'] ?? '') === 'admin';
                
                if ($isAdmin) {
                    $stmt = $this->pdo->query('
                        SELECT 
                            a.*,
                            s.name AS site_name,
                            m.mpan,
                            u.name AS user_name
                        FROM alarms a
                        JOIN sites s ON a.site_id = s.id
                        LEFT JOIN meters m ON a.meter_id = m.id
                        JOIN users u ON a.user_id = u.id
                        ORDER BY a.created_at DESC
                    ');
                    $alarms = $stmt->fetchAll() ?: [];
                } else {
                    $stmt = $this->pdo->prepare('
                        SELECT 
                            a.*,
                            s.name AS site_name,
                            m.mpan
                        FROM alarms a
                        JOIN sites s ON a.site_id = s.id
                        LEFT JOIN meters m ON a.meter_id = m.id
                        WHERE a.user_id = ?
                        ORDER BY a.created_at DESC
                    ');
                    $stmt->execute([$userId]);
                    $alarms = $stmt->fetchAll() ?: [];
                }
            } catch (\Throwable $e) {
                error_log("Error fetching alarms: " . $e->getMessage());
            }
        }

        $flash = $_SESSION['alarm_flash'] ?? null;
        unset($_SESSION['alarm_flash']);

        return $this->view->render($response, 'admin/alarms/index.twig', [
            'page_title' => 'Alarm Management',
            'alarms' => $alarms,
            'flash' => $flash,
        ]);
    }

    /**
     * Show create form
     */
    public function create(Request $request, Response $response): Response
    {
        $sites = [];
        $meters = [];

        if ($this->pdo) {
            // Get sites
            $stmt = $this->pdo->query('SELECT id, name FROM sites WHERE is_active = 1 ORDER BY name');
            $sites = $stmt->fetchAll() ?: [];

            // Get meters
            $stmt = $this->pdo->query('
                SELECT m.id, m.mpan, s.name AS site_name 
                FROM meters m
                JOIN sites s ON m.site_id = s.id
                WHERE m.is_active = 1 
                ORDER BY s.name, m.mpan
            ');
            $meters = $stmt->fetchAll() ?: [];
        }

        return $this->view->render($response, 'admin/alarms/create.twig', [
            'page_title' => 'Create Alarm',
            'sites' => $sites,
            'meters' => $meters,
        ]);
    }

    /**
     * Store a new alarm
     */
    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $user = $_SESSION['user'] ?? null;
        $userId = $user['id'] ?? null;

        if (!$userId) {
            $_SESSION['alarm_flash'] = ['type' => 'error', 'message' => 'User not authenticated'];
            return $response->withHeader('Location', '/admin/alarms')->withStatus(302);
        }

        try {
            // Validate required fields
            if (empty($data['name']) || empty($data['site_id']) || empty($data['threshold_value'])) {
                throw new \Exception('Required fields are missing');
            }

            $meterId = !empty($data['meter_id']) ? (int)$data['meter_id'] : null;
            
            $stmt = $this->pdo->prepare('
                INSERT INTO alarms (
                    user_id, meter_id, site_id, name, description, alarm_type, 
                    threshold_value, period_type, comparison_operator, notification_method, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            
            $stmt->execute([
                $userId,
                $meterId,
                (int)$data['site_id'],
                $data['name'],
                $data['description'] ?? null,
                $data['alarm_type'] ?? 'consumption',
                (float)$data['threshold_value'],
                $data['period_type'] ?? 'daily',
                $data['comparison_operator'] ?? 'greater_than',
                $data['notification_method'] ?? 'both',
                isset($data['is_active']) ? 1 : 0
            ]);

            $alarmId = $this->pdo->lastInsertId();

            // Add recipients if provided
            if (!empty($data['recipients'])) {
                $recipients = array_filter(array_map('trim', explode(',', $data['recipients'])));
                $alarm = Alarm::find($alarmId);
                foreach ($recipients as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $alarm->addRecipient($email);
                    }
                }
            }

            $_SESSION['alarm_flash'] = ['type' => 'success', 'message' => 'Alarm created successfully'];
        } catch (\Exception $e) {
            $_SESSION['alarm_flash'] = ['type' => 'error', 'message' => 'Failed to create alarm: ' . $e->getMessage()];
        }

        return $response->withHeader('Location', '/admin/alarms')->withStatus(302);
    }

    /**
     * Show edit form
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $alarm = null;
        $sites = [];
        $meters = [];
        $recipients = [];

        if ($this->pdo) {
            $alarm = Alarm::find($id);
            
            if ($alarm) {
                $recipients = $alarm->getRecipients();
            }

            // Get sites
            $stmt = $this->pdo->query('SELECT id, name FROM sites WHERE is_active = 1 ORDER BY name');
            $sites = $stmt->fetchAll() ?: [];

            // Get meters
            $stmt = $this->pdo->query('
                SELECT m.id, m.mpan, s.name AS site_name 
                FROM meters m
                JOIN sites s ON m.site_id = s.id
                WHERE m.is_active = 1 
                ORDER BY s.name, m.mpan
            ');
            $meters = $stmt->fetchAll() ?: [];
        }

        return $this->view->render($response, 'admin/alarms/edit.twig', [
            'page_title' => 'Edit Alarm',
            'alarm' => $alarm,
            'sites' => $sites,
            'meters' => $meters,
            'recipients' => $recipients,
        ]);
    }

    /**
     * Update an alarm
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = $request->getParsedBody();

        try {
            $alarm = Alarm::find($id);
            if (!$alarm) {
                throw new \Exception('Alarm not found');
            }

            $meterId = !empty($data['meter_id']) ? (int)$data['meter_id'] : null;

            $alarm->update([
                'meter_id' => $meterId,
                'site_id' => (int)$data['site_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'alarm_type' => $data['alarm_type'] ?? 'consumption',
                'threshold_value' => (float)$data['threshold_value'],
                'period_type' => $data['period_type'] ?? 'daily',
                'comparison_operator' => $data['comparison_operator'] ?? 'greater_than',
                'notification_method' => $data['notification_method'] ?? 'both',
                'is_active' => isset($data['is_active']) ? 1 : 0
            ]);

            // Update recipients
            // First, remove all existing recipients
            $stmt = $this->pdo->prepare('DELETE FROM alarm_recipients WHERE alarm_id = ?');
            $stmt->execute([$id]);

            // Add new recipients
            if (!empty($data['recipients'])) {
                $recipients = array_filter(array_map('trim', explode(',', $data['recipients'])));
                foreach ($recipients as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $alarm->addRecipient($email);
                    }
                }
            }

            $_SESSION['alarm_flash'] = ['type' => 'success', 'message' => 'Alarm updated successfully'];
        } catch (\Exception $e) {
            $_SESSION['alarm_flash'] = ['type' => 'error', 'message' => 'Failed to update alarm: ' . $e->getMessage()];
        }

        return $response->withHeader('Location', '/admin/alarms')->withStatus(302);
    }

    /**
     * Delete an alarm
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        try {
            $alarm = Alarm::find($id);
            if (!$alarm) {
                throw new \Exception('Alarm not found');
            }

            $alarm->delete();
            $_SESSION['alarm_flash'] = ['type' => 'success', 'message' => 'Alarm deleted successfully'];
        } catch (\Exception $e) {
            $_SESSION['alarm_flash'] = ['type' => 'error', 'message' => 'Failed to delete alarm: ' . $e->getMessage()];
        }

        return $response->withHeader('Location', '/admin/alarms')->withStatus(302);
    }

    /**
     * View alarm history and triggers
     */
    public function history(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $alarm = null;
        $triggers = [];

        if ($this->pdo) {
            $alarm = Alarm::find($id);
            
            if ($alarm) {
                $triggers = $alarm->getTriggers(50);
            }
        }

        return $this->view->render($response, 'admin/alarms/history.twig', [
            'page_title' => 'Alarm History',
            'alarm' => $alarm,
            'triggers' => $triggers,
        ]);
    }
}
