<?php
/**
 * eclectyc-energy/app/Domain/Alarms/AlarmNotificationService.php
 * Service to send alarm notifications
 */

namespace App\Domain\Alarms;

use App\Models\Alarm;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class AlarmNotificationService
{
    /**
     * Send notification for a triggered alarm
     */
    public function sendNotification(Alarm $alarm, array $result)
    {
        $method = $alarm->notification_method;
        
        if ($method === 'email' || $method === 'both') {
            $this->sendEmailNotification($alarm, $result);
        }

        if ($method === 'dashboard' || $method === 'both') {
            // Dashboard notifications are handled by displaying alarm_triggers table
            // No action needed here as the trigger is already recorded
        }

        return true;
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(Alarm $alarm, array $result)
    {
        // Get alarm owner
        $user = $alarm->getUser();
        if (!$user) {
            return false;
        }

        // Get all recipients
        $recipients = [$user->email];
        $additionalRecipients = $alarm->getRecipients();
        foreach ($additionalRecipients as $recipient) {
            $recipients[] = $recipient['email'];
        }

        // Build email content
        $subject = 'Alarm Triggered: ' . $alarm->name;
        $body = $this->buildEmailBody($alarm, $result);

        // Send email to all recipients
        foreach ($recipients as $email) {
            $this->sendEmail($email, $subject, $body);
        }

        return true;
    }

    /**
     * Build email body
     */
    private function buildEmailBody(Alarm $alarm, array $result)
    {
        $site = $alarm->getSite();
        $meter = $alarm->getMeter();
        $unit = $alarm->alarm_type === 'consumption' ? 'kWh' : '£';

        $body = "An alarm has been triggered:\n\n";
        $body .= "Alarm Name: {$alarm->name}\n";
        if ($alarm->description) {
            $body .= "Description: {$alarm->description}\n";
        }
        $body .= "Site: {$site->name}\n";
        if ($meter) {
            $body .= "Meter: {$meter->mpan}\n";
        } else {
            $body .= "Level: Site-wide\n";
        }
        $body .= "Type: " . ucfirst($alarm->alarm_type) . "\n";
        $body .= "Period: " . ucfirst($alarm->period_type) . "\n\n";
        
        $body .= "Threshold: " . $this->getOperatorText($alarm->comparison_operator) . " {$alarm->threshold_value} {$unit}\n";
        $body .= "Actual Value: {$result['actual_value']} {$unit}\n\n";
        
        $body .= "Message: {$result['message']}\n\n";
        
        $body .= "---\n";
        $body .= "Eclectyc Energy Management Platform\n";
        $body .= "This is an automated notification. Please do not reply to this email.\n";

        return $body;
    }

    /**
     * Send email using PHPMailer
     */
    private function sendEmail($to, $subject, $body)
    {
        try {
            $mail = new PHPMailer(true);
            
            // Check if we're using SMTP or mail()
            if (getenv('MAIL_SMTP_ENABLED') === 'true') {
                $mail->isSMTP();
                $mail->Host = getenv('MAIL_HOST');
                $mail->SMTPAuth = true;
                $mail->Username = getenv('MAIL_USERNAME');
                $mail->Password = getenv('MAIL_PASSWORD');
                $mail->SMTPSecure = getenv('MAIL_ENCRYPTION') ?: PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = getenv('MAIL_PORT') ?: 587;
            } else {
                $mail->isMail();
            }

            $mail->setFrom(getenv('MAIL_FROM_ADDRESS') ?: 'noreply@eclectyc.energy', getenv('MAIL_FROM_NAME') ?: 'Eclectyc Energy');
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML(false);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Failed to send alarm email to {$to}: " . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Get human-readable operator text
     */
    private function getOperatorText($operator)
    {
        switch ($operator) {
            case 'greater_than':
                return 'greater than';
            case 'less_than':
                return 'less than';
            case 'equals':
                return 'equal to';
            default:
                return $operator;
        }
    }
}
