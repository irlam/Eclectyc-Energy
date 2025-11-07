<?php
/**
 * eclectyc-energy/app/Domain/Orchestration/AlertService.php
 * Sends alerts for scheduler failures and warnings.
 */

namespace App\Domain\Orchestration;

use PDO;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

class AlertService
{
    private PDO $pdo;
    private array $config;

    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    /**
     * Send failure alert.
     */
    public function sendFailureAlert(string $range, Throwable $exception): void
    {
        $subject = "[Eclectyc Energy] Aggregation Failure: {$range}";
        $message = $this->formatFailureMessage($range, $exception);
        
        $this->logAlert('failure', $range, $message);
        
        if ($this->shouldSendEmail()) {
            $this->sendEmail($subject, $message);
        }
    }

    /**
     * Send warning alert.
     */
    public function sendWarning(string $range, array $result): void
    {
        $subject = "[Eclectyc Energy] Aggregation Warning: {$range}";
        $message = $this->formatWarningMessage($range, $result);
        
        $this->logAlert('warning', $range, $message);
        
        if ($this->shouldSendEmail()) {
            $this->sendEmail($subject, $message);
        }
    }

    /**
     * Send summary alert for multiple failures.
     */
    public function sendSummaryAlert(array $results): void
    {
        $subject = "[Eclectyc Energy] Aggregation Summary - Issues Detected";
        $message = $this->formatSummaryMessage($results);
        
        $this->logAlert('summary', 'all', $message);
        
        if ($this->shouldSendEmail()) {
            $this->sendEmail($subject, $message);
        }
    }

    private function formatFailureMessage(string $range, Throwable $exception): string
    {
        return sprintf(
            "Aggregation failed for range: %s\n\nError: %s\n\nTimestamp: %s\n\nStack trace:\n%s",
            $range,
            $exception->getMessage(),
            date('Y-m-d H:i:s'),
            $exception->getTraceAsString()
        );
    }

    private function formatWarningMessage(string $range, array $result): string
    {
        return sprintf(
            "Aggregation completed with warnings for range: %s\n\nWarnings: %d\nErrors: %d\nMeters processed: %d\n\nTimestamp: %s",
            $range,
            $result['warnings'] ?? 0,
            $result['errors'] ?? 0,
            $result['meters_processed'] ?? 0,
            date('Y-m-d H:i:s')
        );
    }

    private function formatSummaryMessage(array $results): string
    {
        $message = "Aggregation Summary - Issues Detected\n\n";
        
        foreach ($results as $range => $result) {
            $status = $result->isSuccess() ? 'SUCCESS' : 'FAILED';
            $message .= sprintf(
                "%s: %s (Duration: %.2fs, Errors: %d, Warnings: %d)\n",
                strtoupper($range),
                $status,
                $result->getDuration(),
                $result->getErrors(),
                $result->getWarnings()
            );
        }
        
        $message .= "\nTimestamp: " . date('Y-m-d H:i:s');
        
        return $message;
    }

    private function logAlert(string $type, string $range, string $message): void
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO scheduler_alerts (alert_type, range_type, message, created_at)
                VALUES (:alert_type, :range_type, :message, NOW())
            ');
            
            $stmt->execute([
                'alert_type' => $type,
                'range_type' => $range,
                'message' => $message,
            ]);
        } catch (\PDOException $e) {
            // Log to file if database insert fails
            error_log("Failed to log alert: " . $e->getMessage());
        }
    }

    private function shouldSendEmail(): bool
    {
        return !empty($this->config['mail_enabled'] ?? $_ENV['MAIL_HOST'] ?? null);
    }

    private function sendEmail(string $subject, string $message): void
    {
        try {
            $mail = new PHPMailer(true);
            
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'] ?? '';
            $mail->Port = $_ENV['MAIL_PORT'] ?? 587;
            $mail->SMTPAuth = !empty($_ENV['MAIL_USERNAME']);
            
            if ($mail->SMTPAuth) {
                $mail->Username = $_ENV['MAIL_USERNAME'];
                $mail->Password = $_ENV['MAIL_PASSWORD'];
            }
            
            $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'] ?? 'tls';
            $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@eclectyc.energy', $_ENV['MAIL_FROM_NAME'] ?? 'Eclectyc Energy');
            
            // Send to admin email if configured
            $adminEmail = $this->config['admin_email'] ?? $_ENV['ADMIN_EMAIL'] ?? null;
            if ($adminEmail) {
                $mail->addAddress($adminEmail);
            }
            
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            $mail->send();
        } catch (\Exception $e) {
            error_log("Failed to send alert email: " . $e->getMessage());
        }
    }
}
