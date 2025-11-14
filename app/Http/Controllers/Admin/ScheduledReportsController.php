<?php
/**
 * eclectyc-energy/app/Http/Controllers/Admin/ScheduledReportsController.php
 * Controller for scheduled report management
 */

namespace App\Http\Controllers\Admin;

use App\Models\ScheduledReport;
use App\Domain\Reports\ReportGenerationService;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class ScheduledReportsController
{
    private Twig $view;
    private ?PDO $pdo;

    public function __construct(Twig $view, ?PDO $pdo)
    {
        $this->view = $view;
        $this->pdo = $pdo;
    }

    /**
     * List all scheduled reports
     */
    public function index(Request $request, Response $response): Response
    {
        $user = $_SESSION['user'] ?? null;
        $userId = $user['id'] ?? null;

        $reports = [];
        if ($this->pdo && $userId) {
            try {
                $isAdmin = ($user['role'] ?? '') === 'admin';
                
                if ($isAdmin) {
                    $stmt = $this->pdo->query('
                        SELECT 
                            sr.*,
                            u.name AS user_name
                        FROM scheduled_reports sr
                        JOIN users u ON sr.user_id = u.id
                        ORDER BY sr.created_at DESC
                    ');
                    $reports = $stmt->fetchAll() ?: [];
                } else {
                    $stmt = $this->pdo->prepare('
                        SELECT * FROM scheduled_reports
                        WHERE user_id = ?
                        ORDER BY created_at DESC
                    ');
                    $stmt->execute([$userId]);
                    $reports = $stmt->fetchAll() ?: [];
                }
            } catch (\Throwable $e) {
                error_log("Error fetching scheduled reports: " . $e->getMessage());
            }
        }

        $flash = $_SESSION['report_flash'] ?? null;
        unset($_SESSION['report_flash']);

        return $this->view->render($response, 'admin/scheduled_reports/index.twig', [
            'page_title' => 'Scheduled Reports',
            'reports' => $reports,
            'flash' => $flash,
        ]);
    }

    /**
     * Show create form
     */
    public function create(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'admin/scheduled_reports/create.twig', [
            'page_title' => 'Create Scheduled Report',
        ]);
    }

    /**
     * Store a new scheduled report
     */
    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $user = $_SESSION['user'] ?? null;
        $userId = $user['id'] ?? null;

        if (!$userId) {
            $_SESSION['report_flash'] = ['type' => 'error', 'message' => 'User not authenticated'];
            return $response->withHeader('Location', '/admin/scheduled-reports')->withStatus(302);
        }

        try {
            if (empty($data['name']) || empty($data['report_type'])) {
                throw new \Exception('Required fields are missing');
            }

            // Build filters JSON
            $filters = [];
            if (!empty($data['start_date'])) {
                $filters['start_date'] = $data['start_date'];
            }
            if (!empty($data['end_date'])) {
                $filters['end_date'] = $data['end_date'];
            }

            $frequency = $data['frequency'] ?? 'manual';
            $dayOfWeek = ($frequency === 'weekly' && isset($data['day_of_week'])) ? (int)$data['day_of_week'] : null;
            $dayOfMonth = ($frequency === 'monthly' && isset($data['day_of_month'])) ? (int)$data['day_of_month'] : null;

            $stmt = $this->pdo->prepare('
                INSERT INTO scheduled_reports (
                    user_id, name, description, report_type, report_format, 
                    frequency, day_of_week, day_of_month, hour_of_day, 
                    is_active, filters
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            
            $stmt->execute([
                $userId,
                $data['name'],
                $data['description'] ?? null,
                $data['report_type'],
                $data['report_format'] ?? 'csv',
                $frequency,
                $dayOfWeek,
                $dayOfMonth,
                (int)($data['hour_of_day'] ?? 8),
                isset($data['is_active']) ? 1 : 0,
                json_encode($filters)
            ]);

            $reportId = $this->pdo->lastInsertId();

            // Add recipients if provided
            if (!empty($data['recipients'])) {
                $recipients = array_filter(array_map('trim', explode(',', $data['recipients'])));
                $report = ScheduledReport::find($reportId);
                foreach ($recipients as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $report->addRecipient($email);
                    }
                }
            }

            // Calculate next run if scheduled
            if ($frequency !== 'manual') {
                $report = ScheduledReport::find($reportId);
                $nextRun = $report->calculateNextRun();
                $report->update(['next_run_at' => $nextRun]);
            }

            $_SESSION['report_flash'] = ['type' => 'success', 'message' => 'Scheduled report created successfully'];
        } catch (\Exception $e) {
            $_SESSION['report_flash'] = ['type' => 'error', 'message' => 'Failed to create report: ' . $e->getMessage()];
        }

        return $response->withHeader('Location', '/admin/scheduled-reports')->withStatus(302);
    }

    /**
     * Show edit form
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $report = null;
        $recipients = [];

        if ($this->pdo) {
            $report = ScheduledReport::find($id);
            
            if ($report) {
                $recipients = $report->getRecipients();
            }
        }

        return $this->view->render($response, 'admin/scheduled_reports/edit.twig', [
            'page_title' => 'Edit Scheduled Report',
            'report' => $report,
            'recipients' => $recipients,
        ]);
    }

    /**
     * Update a scheduled report
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $data = $request->getParsedBody();

        try {
            $report = ScheduledReport::find($id);
            if (!$report) {
                throw new \Exception('Report not found');
            }

            // Build filters JSON
            $filters = [];
            if (!empty($data['start_date'])) {
                $filters['start_date'] = $data['start_date'];
            }
            if (!empty($data['end_date'])) {
                $filters['end_date'] = $data['end_date'];
            }

            $frequency = $data['frequency'] ?? 'manual';
            $dayOfWeek = ($frequency === 'weekly' && isset($data['day_of_week'])) ? (int)$data['day_of_week'] : null;
            $dayOfMonth = ($frequency === 'monthly' && isset($data['day_of_month'])) ? (int)$data['day_of_month'] : null;

            $report->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'report_type' => $data['report_type'],
                'report_format' => $data['report_format'] ?? 'csv',
                'frequency' => $frequency,
                'day_of_week' => $dayOfWeek,
                'day_of_month' => $dayOfMonth,
                'hour_of_day' => (int)($data['hour_of_day'] ?? 8),
                'is_active' => isset($data['is_active']) ? 1 : 0,
                'filters' => json_encode($filters)
            ]);

            // Update recipients
            $stmt = $this->pdo->prepare('DELETE FROM scheduled_report_recipients WHERE scheduled_report_id = ?');
            $stmt->execute([$id]);

            if (!empty($data['recipients'])) {
                $recipients = array_filter(array_map('trim', explode(',', $data['recipients'])));
                foreach ($recipients as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $report->addRecipient($email);
                    }
                }
            }

            // Recalculate next run if scheduled
            if ($frequency !== 'manual') {
                $nextRun = $report->calculateNextRun();
                $report->update(['next_run_at' => $nextRun]);
            }

            $_SESSION['report_flash'] = ['type' => 'success', 'message' => 'Report updated successfully'];
        } catch (\Exception $e) {
            $_SESSION['report_flash'] = ['type' => 'error', 'message' => 'Failed to update report: ' . $e->getMessage()];
        }

        return $response->withHeader('Location', '/admin/scheduled-reports')->withStatus(302);
    }

    /**
     * Delete a scheduled report
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        try {
            $report = ScheduledReport::find($id);
            if (!$report) {
                throw new \Exception('Report not found');
            }

            $report->delete();
            $_SESSION['report_flash'] = ['type' => 'success', 'message' => 'Report deleted successfully'];
        } catch (\Exception $e) {
            $_SESSION['report_flash'] = ['type' => 'error', 'message' => 'Failed to delete report: ' . $e->getMessage()];
        }

        return $response->withHeader('Location', '/admin/scheduled-reports')->withStatus(302);
    }

    /**
     * Manually run a report now
     */
    public function run(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];

        try {
            $report = ScheduledReport::find($id);
            if (!$report) {
                throw new \Exception('Report not found');
            }

            $service = new ReportGenerationService($this->pdo);
            $result = $service->generateAndSend($report);

            if ($result['success']) {
                $_SESSION['report_flash'] = [
                    'type' => 'success', 
                    'message' => "Report generated and sent to {$result['emails_sent']} recipient(s)"
                ];
            } else {
                $_SESSION['report_flash'] = [
                    'type' => 'error', 
                    'message' => 'Failed to generate report: ' . $result['error']
                ];
            }
        } catch (\Exception $e) {
            $_SESSION['report_flash'] = ['type' => 'error', 'message' => 'Failed to run report: ' . $e->getMessage()];
        }

        return $response->withHeader('Location', '/admin/scheduled-reports')->withStatus(302);
    }

    /**
     * View report execution history
     */
    public function history(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $report = null;
        $executions = [];

        if ($this->pdo) {
            $report = ScheduledReport::find($id);
            
            if ($report) {
                $executions = $report->getExecutions(50);
            }
        }

        return $this->view->render($response, 'admin/scheduled_reports/history.twig', [
            'page_title' => 'Report Execution History',
            'report' => $report,
            'executions' => $executions,
        ]);
    }
}
