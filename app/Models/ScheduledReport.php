<?php
/**
 * eclectyc-energy/app/Models/ScheduledReport.php
 * Model for scheduled reports
 */

namespace App\Models;

class ScheduledReport extends BaseModel
{
    protected static $table = 'scheduled_reports';

    /**
     * Get recipients for this scheduled report
     */
    public function getRecipients()
    {
        $stmt = static::$pdo->prepare('
            SELECT * FROM scheduled_report_recipients
            WHERE scheduled_report_id = ? AND is_active = 1
        ');
        $stmt->execute([$this->id]);
        return $stmt->fetchAll();
    }

    /**
     * Get execution history for this report
     */
    public function getExecutions($limit = 20)
    {
        $stmt = static::$pdo->prepare('
            SELECT * FROM report_executions
            WHERE scheduled_report_id = ?
            ORDER BY execution_date DESC
            LIMIT ?
        ');
        $stmt->execute([$this->id, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get user who owns this report
     */
    public function getUser()
    {
        return User::find($this->user_id);
    }

    /**
     * Add a recipient to this report
     */
    public function addRecipient($email)
    {
        $stmt = static::$pdo->prepare('
            INSERT INTO scheduled_report_recipients (scheduled_report_id, email)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE is_active = 1
        ');
        $stmt->execute([$this->id, $email]);
    }

    /**
     * Remove a recipient from this report
     */
    public function removeRecipient($email)
    {
        $stmt = static::$pdo->prepare('
            DELETE FROM scheduled_report_recipients
            WHERE scheduled_report_id = ? AND email = ?
        ');
        $stmt->execute([$this->id, $email]);
    }

    /**
     * Record a report execution
     */
    public function recordExecution($status = 'pending', $errorMessage = null)
    {
        $stmt = static::$pdo->prepare('
            INSERT INTO report_executions (scheduled_report_id, execution_date, status, error_message)
            VALUES (?, NOW(), ?, ?)
        ');
        $stmt->execute([$this->id, $status, $errorMessage]);
        return static::$pdo->lastInsertId();
    }

    /**
     * Update execution status
     */
    public function updateExecution($executionId, $status, $data = [])
    {
        $updates = ['status = ?'];
        $params = [$status];

        if (isset($data['error_message'])) {
            $updates[] = 'error_message = ?';
            $params[] = $data['error_message'];
        }
        if (isset($data['file_path'])) {
            $updates[] = 'file_path = ?';
            $params[] = $data['file_path'];
        }
        if (isset($data['file_size'])) {
            $updates[] = 'file_size = ?';
            $params[] = $data['file_size'];
        }
        if (isset($data['recipients_count'])) {
            $updates[] = 'recipients_count = ?';
            $params[] = $data['recipients_count'];
        }
        if (isset($data['emails_sent'])) {
            $updates[] = 'emails_sent = ?';
            $params[] = $data['emails_sent'];
        }
        if ($status === 'completed' || $status === 'failed') {
            $updates[] = 'completed_at = NOW()';
        }

        $params[] = $executionId;

        $sql = 'UPDATE report_executions SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = static::$pdo->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Calculate next run time based on frequency
     */
    public function calculateNextRun()
    {
        if ($this->frequency === 'manual') {
            return null;
        }

        $now = new \DateTime();
        $next = clone $now;

        switch ($this->frequency) {
            case 'daily':
                $next->modify('+1 day');
                $next->setTime($this->hour_of_day, 0, 0);
                break;

            case 'weekly':
                $next->modify('next ' . $this->getDayName($this->day_of_week));
                $next->setTime($this->hour_of_day, 0, 0);
                break;

            case 'monthly':
                $next->modify('+1 month');
                $next->setDate($next->format('Y'), $next->format('m'), min($this->day_of_month, $next->format('t')));
                $next->setTime($this->hour_of_day, 0, 0);
                break;
        }

        return $next->format('Y-m-d H:i:s');
    }

    /**
     * Get day name from number
     */
    private function getDayName($dayNumber)
    {
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return $days[$dayNumber] ?? 'Monday';
    }

    /**
     * Get filters as array
     */
    public function getFiltersArray()
    {
        return $this->filters ? json_decode($this->filters, true) : [];
    }
}
