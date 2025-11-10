#!/usr/bin/env php
<?php
/**
 * eclectyc-energy/scripts/evaluate_alarms.php
 * Evaluates all active alarms and sends notifications for triggered alarms
 * Should be run daily via cron job
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Config\Database;
use App\Domain\Alarms\AlarmEvaluationService;
use App\Domain\Alarms\AlarmNotificationService;
use App\Models\Alarm;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Parse command line options
$options = getopt('d:v', ['date:', 'verbose']);
$verbose = isset($options['v']) || isset($options['verbose']);
$date = $options['d'] ?? $options['date'] ?? null;

// If no date specified, use yesterday (since we evaluate after the day is complete)
if (!$date) {
    $date = date('Y-m-d', strtotime('-1 day'));
}

if ($verbose) {
    echo "Evaluating alarms for date: {$date}\n";
    echo "---\n";
}

try {
    // Get database connection
    $pdo = Database::getConnection();
    Alarm::setPdo($pdo);

    // Initialize services
    $evaluationService = new AlarmEvaluationService($pdo);
    $notificationService = new AlarmNotificationService();

    // Evaluate all active alarms
    $triggeredAlarms = $evaluationService->evaluateAlarms($date);

    if ($verbose) {
        echo "Found " . count($triggeredAlarms) . " triggered alarm(s)\n\n";
    }

    $totalNotificationsSent = 0;

    foreach ($triggeredAlarms as $item) {
        $alarm = $item['alarm'];
        $result = $item['result'];

        if ($verbose) {
            echo "Alarm #{$alarm->id}: {$alarm->name}\n";
            echo "  " . $result['message'] . "\n";
        }

        // Record the trigger
        $triggerId = $alarm->recordTrigger(
            $date,
            $result['actual_value'],
            $result['threshold_value'],
            $result['message']
        );

        // Send notification
        try {
            $notificationService->sendNotification($alarm, $result);
            
            // Mark notification as sent
            $stmt = $pdo->prepare('
                UPDATE alarm_triggers 
                SET notification_sent = 1, notification_sent_at = NOW() 
                WHERE id = ?
            ');
            $stmt->execute([$triggerId]);
            
            $totalNotificationsSent++;
            
            if ($verbose) {
                echo "  ✓ Notification sent\n";
            }
        } catch (\Exception $e) {
            if ($verbose) {
                echo "  ✗ Failed to send notification: " . $e->getMessage() . "\n";
            }
            error_log("Failed to send alarm notification for alarm #{$alarm->id}: " . $e->getMessage());
        }

        if ($verbose) {
            echo "\n";
        }
    }

    if ($verbose) {
        echo "---\n";
        echo "Summary:\n";
        echo "  Total alarms triggered: " . count($triggeredAlarms) . "\n";
        echo "  Notifications sent: {$totalNotificationsSent}\n";
    }

    // Log to audit_logs
    $stmt = $pdo->prepare('
        INSERT INTO audit_logs (event_type, event_data, created_by)
        VALUES (?, ?, ?)
    ');
    $stmt->execute([
        'alarm_evaluation',
        json_encode([
            'date' => $date,
            'triggered_count' => count($triggeredAlarms),
            'notifications_sent' => $totalNotificationsSent
        ]),
        'system'
    ]);

    exit(0);

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Alarm evaluation error: " . $e->getMessage());
    exit(1);
}
