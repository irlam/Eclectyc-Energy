<?php
/**
 * eclectyc-energy/app/Domain/Ingestion/ImportAlertService.php
 * Service for sending alerts about import job failures and issues
 * Last updated: 2025-11-07
 */

namespace App\Domain\Ingestion;

use PDO;

class ImportAlertService
{
    private PDO $pdo;
    private array $config;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'admin_email' => $_ENV['ADMIN_EMAIL'] ?? null,
            'mail_enabled' => !empty($_ENV['MAIL_HOST']),
            'alert_threshold' => 3, // Alert after N consecutive failures
            'slack_webhook' => $_ENV['SLACK_WEBHOOK_URL'] ?? null,
        ], $config);
    }

    /**
     * Send failure alert for a job
     */
    public function sendFailureAlert(array $job): void
    {
        $subject = 'Import Job Failed: ' . $job['filename'];
        $message = $this->buildFailureMessage($job);

        // Send email if configured
        if ($this->config['mail_enabled'] && $this->config['admin_email']) {
            $this->sendEmail($this->config['admin_email'], $subject, $message);
        }

        // Send to Slack if configured
        if ($this->config['slack_webhook']) {
            $this->sendSlackNotification($subject, $message, 'danger');
        }

        // Log the alert
        error_log("Import Alert: $subject - {$job['error_message']}");
    }

    /**
     * Send batch failure alert for multiple jobs
     */
    public function sendBatchFailureAlert(array $jobs): void
    {
        $count = count($jobs);
        $subject = "Multiple Import Jobs Failed ($count jobs)";
        $message = "The following import jobs have failed:\n\n";

        foreach ($jobs as $job) {
            $message .= "- {$job['filename']} (Batch ID: {$job['batch_id']})\n";
            $message .= "  Error: {$job['error_message']}\n";
            $message .= "  Retries: {$job['retry_count']}/{$job['max_retries']}\n\n";
        }

        if ($this->config['mail_enabled'] && $this->config['admin_email']) {
            $this->sendEmail($this->config['admin_email'], $subject, $message);
        }

        if ($this->config['slack_webhook']) {
            $this->sendSlackNotification($subject, $message, 'danger');
        }

        error_log("Import Alert: $subject");
    }

    /**
     * Send stuck jobs alert
     */
    public function sendStuckJobsAlert(array $stuckJobs): void
    {
        $count = count($stuckJobs);
        $subject = "Import Jobs Stuck in Processing ($count jobs)";
        $message = "The following import jobs are stuck in processing state:\n\n";

        foreach ($stuckJobs as $job) {
            $message .= "- {$job['filename']} (Batch ID: {$job['batch_id']})\n";
            $message .= "  Stuck for: {$job['minutes_stuck']} minutes\n";
            $message .= "  Started at: {$job['started_at']}\n\n";
        }

        if ($this->config['mail_enabled'] && $this->config['admin_email']) {
            $this->sendEmail($this->config['admin_email'], $subject, $message);
        }

        if ($this->config['slack_webhook']) {
            $this->sendSlackNotification($subject, $message, 'warning');
        }

        error_log("Import Alert: $subject");
    }

    /**
     * Send high failure rate alert
     */
    public function sendHighFailureRateAlert(float $failureRate, int $hours): void
    {
        $subject = 'High Import Failure Rate Detected';
        $message = sprintf(
            "Import failure rate is %.1f%% over the last %d hours.\n\n" .
            "This may indicate a systemic issue that needs investigation.\n" .
            "Common causes:\n" .
            "- Database connectivity issues\n" .
            "- File format changes\n" .
            "- Missing or invalid meter data\n" .
            "- Worker process issues\n",
            $failureRate,
            $hours
        );

        if ($this->config['mail_enabled'] && $this->config['admin_email']) {
            $this->sendEmail($this->config['admin_email'], $subject, $message);
        }

        if ($this->config['slack_webhook']) {
            $this->sendSlackNotification($subject, $message, 'danger');
        }

        error_log("Import Alert: $subject - Rate: {$failureRate}%");
    }

    /**
     * Send queue backlog alert
     */
    public function sendQueueBacklogAlert(int $queueDepth): void
    {
        $subject = 'Import Queue Backlog Alert';
        $message = sprintf(
            "Import queue has %d jobs waiting for processing.\n\n" .
            "This may indicate:\n" .
            "- Worker process is not running or has stopped\n" .
            "- Worker is processing jobs too slowly\n" .
            "- Large batch of imports queued\n\n" .
            "Check worker status: php scripts/process_import_jobs.php --once\n",
            $queueDepth
        );

        if ($this->config['mail_enabled'] && $this->config['admin_email']) {
            $this->sendEmail($this->config['admin_email'], $subject, $message);
        }

        if ($this->config['slack_webhook']) {
            $this->sendSlackNotification($subject, $message, 'warning');
        }

        error_log("Import Alert: $subject - Queue depth: $queueDepth");
    }

    /**
     * Build detailed failure message
     */
    private function buildFailureMessage(array $job): string
    {
        $message = "Import job has failed after {$job['retry_count']} retries.\n\n";
        $message .= "Details:\n";
        $message .= "- Batch ID: {$job['batch_id']}\n";
        $message .= "- Filename: {$job['filename']}\n";
        $message .= "- Import Type: {$job['import_type']}\n";
        $message .= "- Queued At: {$job['queued_at']}\n";
        
        if (!empty($job['started_at'])) {
            $message .= "- Started At: {$job['started_at']}\n";
        }
        
        if (!empty($job['completed_at'])) {
            $message .= "- Failed At: {$job['completed_at']}\n";
        }
        
        $message .= "\nError:\n{$job['error_message']}\n";
        
        if (!empty($job['notes'])) {
            $message .= "\nNotes: {$job['notes']}\n";
        }
        
        if (!empty($job['last_error']) && $job['last_error'] !== $job['error_message']) {
            $message .= "\nPrevious Error:\n{$job['last_error']}\n";
        }
        
        return $message;
    }

    /**
     * Send email notification
     */
    private function sendEmail(string $to, string $subject, string $message): bool
    {
        $headers = [
            'From: Eclectyc Energy <noreply@eclectyc.energy>',
            'X-Mailer: PHP/' . phpversion(),
            'Content-Type: text/plain; charset=UTF-8',
        ];

        return mail($to, $subject, $message, implode("\r\n", $headers));
    }

    /**
     * Send Slack notification
     */
    private function sendSlackNotification(string $title, string $message, string $color = 'warning'): bool
    {
        if (!$this->config['slack_webhook']) {
            return false;
        }

        $payload = json_encode([
            'attachments' => [
                [
                    'color' => $color,
                    'title' => $title,
                    'text' => $message,
                    'footer' => 'Eclectyc Energy Import System',
                    'ts' => time(),
                ],
            ],
        ]);

        $ch = curl_init($this->config['slack_webhook']);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }
}
