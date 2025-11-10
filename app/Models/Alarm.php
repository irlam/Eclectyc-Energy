<?php
/**
 * eclectyc-energy/app/Models/Alarm.php
 * Model for alarm configurations
 */

namespace App\Models;

class Alarm extends BaseModel
{
    protected static $table = 'alarms';

    /**
     * Get triggers for this alarm
     */
    public function getTriggers($limit = 10)
    {
        $stmt = static::$pdo->prepare('
            SELECT * FROM alarm_triggers
            WHERE alarm_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ');
        $stmt->execute([$this->id, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get recipients for this alarm
     */
    public function getRecipients()
    {
        $stmt = static::$pdo->prepare('
            SELECT * FROM alarm_recipients
            WHERE alarm_id = ? AND is_active = 1
        ');
        $stmt->execute([$this->id]);
        return $stmt->fetchAll();
    }

    /**
     * Get meter details if this is a meter-level alarm
     */
    public function getMeter()
    {
        if (!$this->meter_id) {
            return null;
        }
        return Meter::find($this->meter_id);
    }

    /**
     * Get site details
     */
    public function getSite()
    {
        return Site::find($this->site_id);
    }

    /**
     * Get user who owns this alarm
     */
    public function getUser()
    {
        return User::find($this->user_id);
    }

    /**
     * Record a trigger event
     */
    public function recordTrigger($date, $actualValue, $thresholdValue, $message = null)
    {
        $stmt = static::$pdo->prepare('
            INSERT INTO alarm_triggers (alarm_id, trigger_date, actual_value, threshold_value, message)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $this->id,
            $date,
            $actualValue,
            $thresholdValue,
            $message
        ]);

        // Update last triggered timestamp
        $this->update(['last_triggered_at' => date('Y-m-d H:i:s')]);

        return static::$pdo->lastInsertId();
    }

    /**
     * Add a recipient to this alarm
     */
    public function addRecipient($email)
    {
        $stmt = static::$pdo->prepare('
            INSERT INTO alarm_recipients (alarm_id, email)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE is_active = 1
        ');
        $stmt->execute([$this->id, $email]);
    }

    /**
     * Remove a recipient from this alarm
     */
    public function removeRecipient($email)
    {
        $stmt = static::$pdo->prepare('
            DELETE FROM alarm_recipients
            WHERE alarm_id = ? AND email = ?
        ');
        $stmt->execute([$this->id, $email]);
    }
}
