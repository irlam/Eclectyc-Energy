<?php
/**
 * eclectyc-energy/app/Domain/Alarms/AlarmEvaluationService.php
 * Service to evaluate alarms against actual consumption/cost data
 */

namespace App\Domain\Alarms;

use App\Models\Alarm;
use PDO;

class AlarmEvaluationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Evaluate all active alarms for a given date
     */
    public function evaluateAlarms($date = null)
    {
        $date = $date ?? date('Y-m-d');
        $triggeredAlarms = [];

        // Get all active alarms
        $stmt = $this->pdo->prepare('
            SELECT * FROM alarms
            WHERE is_active = 1
        ');
        $stmt->execute();
        $alarms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($alarms as $alarmData) {
            $alarm = new Alarm($alarmData);
            $result = $this->evaluateAlarm($alarm, $date);
            
            if ($result['triggered']) {
                $triggeredAlarms[] = [
                    'alarm' => $alarm,
                    'result' => $result
                ];
            }
        }

        return $triggeredAlarms;
    }

    /**
     * Evaluate a single alarm
     */
    public function evaluateAlarm(Alarm $alarm, $date)
    {
        // Get the aggregation period
        $periodData = $this->getPeriodData($alarm, $date);
        
        if (!$periodData) {
            return [
                'triggered' => false,
                'reason' => 'No data available for period'
            ];
        }

        // Get actual value based on alarm type
        $actualValue = $this->getActualValue($alarm, $periodData);
        
        // Check if threshold is breached
        $triggered = $this->checkThreshold($actualValue, $alarm->threshold_value, $alarm->comparison_operator);

        return [
            'triggered' => $triggered,
            'actual_value' => $actualValue,
            'threshold_value' => $alarm->threshold_value,
            'period_data' => $periodData,
            'message' => $this->buildMessage($alarm, $actualValue, $triggered)
        ];
    }

    /**
     * Get aggregated data for the alarm period
     */
    private function getPeriodData(Alarm $alarm, $date)
    {
        $table = $this->getAggregationTable($alarm->period_type);
        
        // Build query based on meter or site level
        if ($alarm->meter_id) {
            $stmt = $this->pdo->prepare("
                SELECT * FROM {$table}
                WHERE meter_id = ? AND date = ?
            ");
            $stmt->execute([$alarm->meter_id, $date]);
        } else {
            // Site-level: sum all meters for the site
            $stmt = $this->pdo->prepare("
                SELECT 
                    SUM(total_consumption) as total_consumption,
                    SUM(total_cost) as total_cost,
                    ? as date
                FROM {$table} da
                JOIN meters m ON da.meter_id = m.id
                WHERE m.site_id = ? AND da.date = ?
                GROUP BY date
            ");
            $stmt->execute([$date, $alarm->site_id, $date]);
        }

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get the actual value to compare against threshold
     */
    private function getActualValue(Alarm $alarm, $periodData)
    {
        if ($alarm->alarm_type === 'consumption') {
            return (float) ($periodData['total_consumption'] ?? 0);
        } else { // cost
            return (float) ($periodData['total_cost'] ?? 0);
        }
    }

    /**
     * Check if the threshold is breached
     */
    private function checkThreshold($actualValue, $thresholdValue, $operator)
    {
        switch ($operator) {
            case 'greater_than':
                return $actualValue > $thresholdValue;
            case 'less_than':
                return $actualValue < $thresholdValue;
            case 'equals':
                return abs($actualValue - $thresholdValue) < 0.01; // Allow small floating point differences
            default:
                return false;
        }
    }

    /**
     * Get the appropriate aggregation table name
     */
    private function getAggregationTable($periodType)
    {
        switch ($periodType) {
            case 'daily':
                return 'daily_aggregations';
            case 'weekly':
                return 'weekly_aggregations';
            case 'monthly':
                return 'monthly_aggregations';
            default:
                return 'daily_aggregations';
        }
    }

    /**
     * Build a message describing the alarm trigger
     */
    private function buildMessage(Alarm $alarm, $actualValue, $triggered)
    {
        $unit = $alarm->alarm_type === 'consumption' ? 'kWh' : '£';
        $operator = $this->getOperatorText($alarm->comparison_operator);
        
        if ($triggered) {
            return sprintf(
                'Alarm "%s" triggered: %s %.2f %s (threshold: %s %.2f %s)',
                $alarm->name,
                ucfirst($alarm->alarm_type),
                $actualValue,
                $unit,
                $operator,
                $alarm->threshold_value,
                $unit
            );
        } else {
            return sprintf(
                'Alarm "%s" not triggered: %s %.2f %s (threshold: %s %.2f %s)',
                $alarm->name,
                ucfirst($alarm->alarm_type),
                $actualValue,
                $unit,
                $operator,
                $alarm->threshold_value,
                $unit
            );
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
