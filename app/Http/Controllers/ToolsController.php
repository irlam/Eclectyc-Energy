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
        
        // Get actual health data from API for accurate card display
        $healthData = $this->getHealthDataFromApi();

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

    /**
     * View application logs
     */
    public function viewLogs(Request $request, Response $response): Response
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $logFile = $basePath . '/logs/php-error.log';
        
        // Get query parameters
        $queryParams = $request->getQueryParams();
        $lines = isset($queryParams['lines']) ? max(10, min(1000, (int)$queryParams['lines'])) : 100;
        $search = $queryParams['search'] ?? '';
        $level = $queryParams['level'] ?? '';
        
        $logContent = '';
        $logExists = false;
        $logSize = 0;
        $error = null;
        
        // Get flash message
        $flash = $_SESSION['tools_flash'] ?? null;
        unset($_SESSION['tools_flash']);
        
        if (file_exists($logFile)) {
            $logExists = true;
            $logSize = filesize($logFile);
            
            try {
                // Read the last N lines from the file
                if ($logSize > 0) {
                    $logContent = $this->readLastLines($logFile, $lines);
                    
                    // Apply filters
                    if (!empty($search) || !empty($level)) {
                        $logLines = explode("\n", $logContent);
                        $filteredLines = [];
                        
                        foreach ($logLines as $line) {
                            $matchesSearch = empty($search) || stripos($line, $search) !== false;
                            $matchesLevel = empty($level) || stripos($line, $level) !== false;
                            
                            if ($matchesSearch && $matchesLevel) {
                                $filteredLines[] = $line;
                            }
                        }
                        
                        $logContent = implode("\n", $filteredLines);
                    }
                } else {
                    $logContent = '(Log file is empty)';
                }
            } catch (\Exception $e) {
                $error = 'Failed to read log file: ' . $e->getMessage();
                $logContent = '';
            }
        } else {
            $error = 'Log file does not exist: ' . $logFile;
        }
        
        return $this->view->render($response, 'tools/logs.twig', [
            'page_title' => 'Application Logs',
            'log_content' => $logContent,
            'log_exists' => $logExists,
            'log_size' => $logSize,
            'log_size_mb' => $logSize > 0 ? round($logSize / 1048576, 2) : 0,
            'error' => $error,
            'flash' => $flash,
            'filters' => [
                'lines' => $lines,
                'search' => $search,
                'level' => $level,
            ],
        ]);
    }

    /**
     * Clear application logs
     */
    public function clearLogs(Request $request, Response $response): Response
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $logFile = $basePath . '/logs/php-error.log';
        
        try {
            if (file_exists($logFile)) {
                // Create a backup before clearing
                $backupFile = $logFile . '.backup.' . date('Y-m-d_H-i-s');
                copy($logFile, $backupFile);
                
                // Clear the log file
                file_put_contents($logFile, '');
                
                $_SESSION['tools_flash'] = [
                    'type' => 'success',
                    'message' => 'Log file cleared successfully. Backup created at: ' . basename($backupFile),
                ];
            } else {
                $_SESSION['tools_flash'] = [
                    'type' => 'warning',
                    'message' => 'Log file does not exist.',
                ];
            }
        } catch (\Exception $e) {
            $_SESSION['tools_flash'] = [
                'type' => 'error',
                'message' => 'Failed to clear log file: ' . $e->getMessage(),
            ];
        }
        
        return $response->withHeader('Location', '/tools/logs')->withStatus(302);
    }

    /**
     * Read last N lines from a file efficiently
     */
    private function readLastLines(string $filepath, int $lines = 100): string
    {
        $file = new \SplFileObject($filepath, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $offset = max(0, $lastLine - $lines);
        
        $result = [];
        for ($i = $offset; $i <= $lastLine; $i++) {
            $file->seek($i);
            $line = $file->current();
            if (!empty(trim($line))) {
                $result[] = $line;
            }
        }
        
        return implode('', $result);
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

    /**
     * Get health data directly from the API endpoint
     */
    private function getHealthDataFromApi(): array
    {
        $data = [
            'status' => 'unknown',
            'healthy' => 0,
            'degraded' => 0,
            'critical' => 0,
        ];

        try {
            // Try to get health data from the health API endpoint
            $healthUrl = ($_ENV['APP_URL'] ?? 'http://localhost') . '/api/health';
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'ignore_errors' => true,
                ],
            ]);
            
            $response = @file_get_contents($healthUrl, false, $context);
            
            if ($response !== false) {
                $health = json_decode($response, true);
                
                if (is_array($health)) {
                    $data['status'] = $health['status'] ?? 'unknown';
                    
                    // Count checks by status
                    if (isset($health['checks']) && is_array($health['checks'])) {
                        foreach ($health['checks'] as $check) {
                            $checkStatus = $check['status'] ?? 'unknown';
                            
                            if ($checkStatus === 'healthy') {
                                $data['healthy']++;
                            } elseif ($checkStatus === 'degraded') {
                                $data['degraded']++;
                            } elseif ($checkStatus === 'critical') {
                                $data['critical']++;
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Failed to get health data from API: ' . $e->getMessage());
        }

        return $data;
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
