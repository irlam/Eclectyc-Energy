<?php
/**
 * eclectyc-energy/app/Http/Controllers/ToolsController.php
 * Bridges CLI tooling output into the dashboard.
 */

namespace App\Http\Controllers;

use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class ToolsController
{
    private Twig $view;
    private ?PDO $pdo;

    public function __construct(Twig $view, ?PDO $pdo = null)
    {
        $this->view = $view;
        $this->pdo = $pdo;
    }

    /**
     * Tools dashboard - show all available tools
     */
    public function index(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'tools/index.twig', [
            'page_title' => 'System Tools',
        ]);
    }

    public function checkStructure(Request $request, Response $response): Response
    {
        $output = $this->runTool('tools/check-structure.php');

        return $this->view->render($response, 'tools/check.twig', [
            'title' => 'Structure Check',
            'output' => $output,
        ]);
    }

    public function showStructure(Request $request, Response $response): Response
    {
        $output = $this->runTool('tools/show-structure.php');

        return $this->view->render($response, 'tools/show.twig', [
            'title' => 'Structure Overview',
            'output' => $output,
        ]);
    }

    /**
     * System health diagnostics
     */
    public function systemHealth(Request $request, Response $response): Response
    {
        $verbose = $request->getQueryParams()['verbose'] ?? false;
        
        $args = $verbose ? '--verbose' : '';
        $output = $this->runToolWithArgs('scripts/check_system_health.php', $args);
        
        // Parse output to extract structured data
        $healthData = $this->parseHealthOutput($output);

        return $this->view->render($response, 'tools/system_health.twig', [
            'page_title' => 'System Health Diagnostics',
            'output' => $output,
            'health_data' => $healthData,
            'verbose' => $verbose,
        ]);
    }

    /**
     * Email testing tool
     */
    public function emailTest(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $recipient = $data['recipient'] ?? $_ENV['ADMIN_EMAIL'] ?? '';
        $method = $data['method'] ?? 'phpmailer';
        
        $output = null;
        $success = false;
        
        if ($request->getMethod() === 'POST' && !empty($recipient)) {
            $args = escapeshellarg($recipient) . ' --method=' . escapeshellarg($method);
            $output = $this->runToolWithArgs('scripts/test_email.php', $args);
            $success = strpos($output, '✅ Email sent successfully') !== false;
        }

        return $this->view->render($response, 'tools/email_test.twig', [
            'page_title' => 'Email Testing',
            'output' => $output,
            'success' => $success,
            'recipient' => $recipient,
            'method' => $method,
            'mail_configured' => !empty($_ENV['MAIL_HOST']),
        ]);
    }

    /**
     * CLI Tools documentation page
     */
    public function cliTools(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'tools/cli_tools.twig', [
            'page_title' => 'CLI Tools',
        ]);
    }

    /**
     * Cron Jobs setup documentation page
     */
    public function cronJobs(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'tools/cron_jobs.twig', [
            'page_title' => 'Cron Jobs Setup',
        ]);
    }

    private function runTool(string $script): string
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $path = $basePath . '/' . ltrim($script, '/');

        if (!is_file($path)) {
            return 'Unable to locate script: ' . $script;
        }

        $result = shell_exec('php ' . escapeshellarg($path) . ' 2>&1');
        return $result !== null ? trim($result) : 'No output available';
    }

    private function runToolWithArgs(string $script, string $args = ''): string
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $path = $basePath . '/' . ltrim($script, '/');

        if (!is_file($path)) {
            return 'Unable to locate script: ' . $script;
        }

        $command = 'php ' . escapeshellarg($path) . ' ' . $args . ' 2>&1';
        $result = shell_exec($command);
        return $result !== null ? trim($result) : 'No output available';
    }

    private function parseHealthOutput(string $output): array
    {
        $data = [
            'status' => 'unknown',
            'healthy' => 0,
            'degraded' => 0,
            'critical' => 0,
        ];

        // Extract overall status
        if (preg_match('/Overall Status: (.+?) (HEALTHY|DEGRADED|CRITICAL|UNKNOWN)/i', $output, $matches)) {
            $data['status'] = strtolower($matches[2]);
        }

        // Count health check statuses
        $data['healthy'] = substr_count($output, '✅');
        $data['degraded'] = substr_count($output, '⚠️');
        $data['critical'] = substr_count($output, '❌');

        return $data;
    }
}
