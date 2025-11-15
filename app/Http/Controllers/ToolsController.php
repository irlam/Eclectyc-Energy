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
     * View cron logs from the database
     */
    public function cronLogs(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $page = isset($queryParams['page']) ? max(1, (int)$queryParams['page']) : 1;
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        
        $jobName = $queryParams['job_name'] ?? '';
        $jobType = $queryParams['job_type'] ?? '';
        $status = $queryParams['status'] ?? '';
        
        // Get flash message
        $flash = $_SESSION['tools_flash'] ?? null;
        unset($_SESSION['tools_flash']);
        
        $logs = [];
        $totalLogs = 0;
        $totalPages = 1;
        
        if ($this->pdo) {
            try {
                // Build WHERE clause
                $where = [];
                $params = [];
                
                if (!empty($jobName)) {
                    $where[] = 'job_name LIKE :job_name';
                    $params['job_name'] = '%' . $jobName . '%';
                }
                
                if (!empty($jobType)) {
                    $where[] = 'job_type = :job_type';
                    $params['job_type'] = $jobType;
                }
                
                if (!empty($status)) {
                    $where[] = 'status = :status';
                    $params['status'] = $status;
                }
                
                $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
                
                // Get total count
                $countSql = "SELECT COUNT(*) FROM cron_logs $whereClause";
                $countStmt = $this->pdo->prepare($countSql);
                $countStmt->execute($params);
                $totalLogs = (int)$countStmt->fetchColumn();
                $totalPages = max(1, ceil($totalLogs / $perPage));
                
                // Get logs
                $sql = "SELECT * FROM cron_logs $whereClause ORDER BY start_time DESC LIMIT :limit OFFSET :offset";
                $stmt = $this->pdo->prepare($sql);
                
                foreach ($params as $key => $value) {
                    $stmt->bindValue(':' . $key, $value);
                }
                $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                
                $stmt->execute();
                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Parse JSON log_data for each log
                foreach ($logs as &$log) {
                    if (!empty($log['log_data'])) {
                        $log['log_data_parsed'] = json_decode($log['log_data'], true);
                    }
                }
            } catch (\PDOException $e) {
                error_log('Failed to fetch cron logs: ' . $e->getMessage());
            }
        }
        
        // Define available cron jobs with their commands
        $cronJobs = $this->getCronJobDefinitions();
        
        return $this->view->render($response, 'tools/cron_logs.twig', [
            'page_title' => 'Cron Logs',
            'logs' => $logs,
            'total_logs' => $totalLogs,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'filters' => [
                'job_name' => $jobName,
                'job_type' => $jobType,
                'status' => $status,
            ],
            'cron_jobs' => $cronJobs,
            'flash' => $flash,
        ]);
    }

    /**
     * Run a cron job manually
     */
    public function runCronJob(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $jobKey = $data['job_key'] ?? '';
        
        $cronJobs = $this->getCronJobDefinitions();
        
        if (!isset($cronJobs[$jobKey])) {
            $_SESSION['tools_flash'] = [
                'type' => 'error',
                'message' => 'Invalid cron job specified.',
            ];
            return $response->withHeader('Location', '/tools/cron-logs')->withStatus(302);
        }
        
        $job = $cronJobs[$jobKey];
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        
        try {
            // Build the command
            $scriptPath = $basePath . '/' . ltrim($job['script'], '/');
            
            if (!file_exists($scriptPath)) {
                throw new \Exception('Script file not found: ' . $job['script']);
            }
            
            $args = $job['args'] ?? '';
            $command = 'php ' . escapeshellarg($scriptPath) . ' ' . $args . ' > /dev/null 2>&1 &';
            
            // Execute in background
            exec($command);
            
            $_SESSION['tools_flash'] = [
                'type' => 'success',
                'message' => 'Cron job "' . $job['name'] . '" has been started in the background. Check the logs below for results.',
            ];
        } catch (\Exception $e) {
            $_SESSION['tools_flash'] = [
                'type' => 'error',
                'message' => 'Failed to run cron job: ' . $e->getMessage(),
            ];
        }
        
        return $response->withHeader('Location', '/tools/cron-logs')->withStatus(302);
    }

    /**
     * Get cron job definitions
     */
    private function getCronJobDefinitions(): array
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $phpBin = PHP_BINARY;
        
        return [
            'import_jobs' => [
                'name' => 'Background Import Processing',
                'script' => 'scripts/process_import_jobs.php',
                'args' => '--once',
                'schedule' => '* * * * *',
                'description' => 'Processes queued import jobs asynchronously',
                'job_type' => 'import',
                'command_template' => "cd \${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/process_import_jobs.php --once >> httpdocs/logs/import_worker.log 2>&1",
                'local_command' => "cd {$basePath} && {$phpBin} scripts/process_import_jobs.php --once",
            ],
            'aggregate_daily' => [
                'name' => 'Daily Data Aggregation',
                'script' => 'scripts/aggregate_cron.php',
                'args' => '--range=daily',
                'schedule' => '0 1 * * *',
                'description' => 'Aggregates half-hourly data into daily summaries',
                'job_type' => 'daily',
                'command_template' => "cd \${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/aggregate_cron.php --range=daily >> httpdocs/logs/aggregate_daily.log 2>&1",
                'local_command' => "cd {$basePath} && {$phpBin} scripts/aggregate_cron.php --range=daily",
            ],
            'aggregate_weekly' => [
                'name' => 'Weekly Data Aggregation',
                'script' => 'scripts/aggregate_cron.php',
                'args' => '--range=weekly',
                'schedule' => '0 2 * * 1',
                'description' => 'Aggregates daily data into weekly summaries',
                'job_type' => 'weekly',
                'command_template' => "cd \${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/aggregate_cron.php --range=weekly >> httpdocs/logs/aggregate_weekly.log 2>&1",
                'local_command' => "cd {$basePath} && {$phpBin} scripts/aggregate_cron.php --range=weekly",
            ],
            'aggregate_monthly' => [
                'name' => 'Monthly Data Aggregation',
                'script' => 'scripts/aggregate_cron.php',
                'args' => '--range=monthly',
                'schedule' => '0 3 1 * *',
                'description' => 'Aggregates daily data into monthly summaries',
                'job_type' => 'monthly',
                'command_template' => "cd \${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/aggregate_cron.php --range=monthly >> httpdocs/logs/aggregate_monthly.log 2>&1",
                'local_command' => "cd {$basePath} && {$phpBin} scripts/aggregate_cron.php --range=monthly",
            ],
            'aggregate_annual' => [
                'name' => 'Annual Data Aggregation',
                'script' => 'scripts/aggregate_cron.php',
                'args' => '--range=annual',
                'schedule' => '0 4 1 1 *',
                'description' => 'Aggregates daily data into annual summaries',
                'job_type' => 'annual',
                'command_template' => "cd \${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/aggregate_cron.php --range=annual >> httpdocs/logs/aggregate_annual.log 2>&1",
                'local_command' => "cd {$basePath} && {$phpBin} scripts/aggregate_cron.php --range=annual",
            ],
            'cleanup_logs' => [
                'name' => 'Log Cleanup',
                'script' => 'scripts/cleanup_logs.php',
                'args' => '',
                'schedule' => '0 0 * * 0',
                'description' => 'Cleans up old log files and database logs',
                'job_type' => 'cleanup',
                'command_template' => "cd \${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/cleanup_logs.php >> httpdocs/logs/cleanup.log 2>&1",
                'local_command' => "cd {$basePath} && {$phpBin} scripts/cleanup_logs.php",
            ],
            'cleanup_import_jobs' => [
                'name' => 'Import Jobs Cleanup',
                'script' => 'scripts/cleanup_import_jobs.php',
                'args' => '',
                'schedule' => '0 2 * * *',
                'description' => 'Cleans up old completed import jobs',
                'job_type' => 'cleanup',
                'command_template' => "cd \${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/cleanup_import_jobs.php >> httpdocs/logs/cleanup.log 2>&1",
                'local_command' => "cd {$basePath} && {$phpBin} scripts/cleanup_import_jobs.php",
            ],
            'carbon_intensity' => [
                'name' => 'Fetch Carbon Intensity',
                'script' => 'scripts/fetch_carbon_intensity.php',
                'args' => '',
                'schedule' => '*/30 * * * *',
                'description' => 'Fetches carbon intensity data from National Grid',
                'job_type' => 'other',
                'command_template' => "cd \${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/fetch_carbon_intensity.php >> httpdocs/logs/carbon_intensity.log 2>&1",
                'local_command' => "cd {$basePath} && {$phpBin} scripts/fetch_carbon_intensity.php",
            ],
            'scheduled_reports' => [
                'name' => 'Process Scheduled Reports',
                'script' => 'scripts/process_scheduled_reports.php',
                'args' => '',
                'schedule' => '0 6 * * *',
                'description' => 'Generates and sends scheduled reports',
                'job_type' => 'other',
                'command_template' => "cd \${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/process_scheduled_reports.php >> httpdocs/logs/scheduled_reports.log 2>&1",
                'local_command' => "cd {$basePath} && {$phpBin} scripts/process_scheduled_reports.php",
            ],
            'evaluate_alarms' => [
                'name' => 'Evaluate Alarms',
                'script' => 'scripts/evaluate_alarms.php',
                'args' => '',
                'schedule' => '*/15 * * * *',
                'description' => 'Evaluates alarm conditions and triggers alerts',
                'job_type' => 'other',
                'command_template' => "cd \${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/evaluate_alarms.php >> httpdocs/logs/alarms.log 2>&1",
                'local_command' => "cd {$basePath} && {$phpBin} scripts/evaluate_alarms.php",
            ],
            'export_sftp' => [
                'name' => 'SFTP Export',
                'script' => 'scripts/export_sftp.php',
                'args' => '',
                'schedule' => '0 5 * * *',
                'description' => 'Exports data to configured SFTP servers',
                'job_type' => 'export',
                'command_template' => "cd \${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/export_sftp.php >> httpdocs/logs/sftp_export.log 2>&1",
                'local_command' => "cd {$basePath} && {$phpBin} scripts/export_sftp.php",
            ],
            'ai_insights' => [
                'name' => 'Generate AI Insights',
                'script' => 'scripts/generate_ai_insights.php',
                'args' => '',
                'schedule' => '0 7 * * *',
                'description' => 'Generates AI-powered insights and recommendations',
                'job_type' => 'other',
                'command_template' => "cd \${HTTPD_VHOSTS_D}/eclectyc.energy && /usr/local/php84/bin/php httpdocs/scripts/generate_ai_insights.php >> httpdocs/logs/ai_insights.log 2>&1",
                'local_command' => "cd {$basePath} && {$phpBin} scripts/generate_ai_insights.php",
            ],
        ];
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
