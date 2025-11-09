<?php
/**
 * eclectyc-energy/app/models/Meter.php
 * Meter model for database operations
 * Last updated: 06/11/2024 14:45:00
 */

namespace App\Models;

class Meter extends BaseModel
{
    protected static string $table = 'meters';
    
    /**
     * Get site relationship
     */
    public function site(): ?Site
    {
        return Site::find($this->site_id);
    }
    
    /**
     * Get supplier relationship
     */
    public function supplier(): ?Supplier
    {
        return $this->supplier_id ? Supplier::find($this->supplier_id) : null;
    }
    
    /**
     * Get readings for date range
     */
    public function getReadings(string $startDate, string $endDate): array
    {
        $db = \App\Config\Database::getConnection();
        if (!$db) return [];
        
        $stmt = $db->prepare("
            SELECT * FROM meter_readings 
            WHERE meter_id = ? 
            AND reading_date BETWEEN ? AND ?
            ORDER BY reading_date, reading_time
        ");
        
        $stmt->execute([$this->id, $startDate, $endDate]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get latest reading
     */
    public function getLatestReading(): ?array
    {
        $db = \App\Config\Database::getConnection();
        if (!$db) return null;
        
        $stmt = $db->prepare("
            SELECT * FROM meter_readings 
            WHERE meter_id = ? 
            ORDER BY reading_date DESC, reading_time DESC 
            LIMIT 1
        ");
        
        $stmt->execute([$this->id]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Calculate consumption for period
     */
    public function calculateConsumption(string $startDate, string $endDate): float
    {
        $db = \App\Config\Database::getConnection();
        if (!$db) return 0.0;
        
        $stmt = $db->prepare("
            SELECT SUM(reading_value) as total 
            FROM meter_readings 
            WHERE meter_id = ? 
            AND reading_date BETWEEN ? AND ?
        ");
        
        $stmt->execute([$this->id, $startDate, $endDate]);
        
        $result = $stmt->fetch();
        
        return (float) ($result['total'] ?? 0);
    }
    
    /**
     * Calculate consumption per metric variable
     * Returns consumption normalized by the metric variable value
     * Example: kWh per square meter, kWh per bed, etc.
     */
    public function calculateConsumptionPerMetric(string $startDate, string $endDate): ?float
    {
        if (!$this->metric_variable_value || $this->metric_variable_value <= 0) {
            return null;
        }
        
        $totalConsumption = $this->calculateConsumption($startDate, $endDate);
        
        return $totalConsumption / (float) $this->metric_variable_value;
    }
    
    /**
     * Check if meter has a metric variable configured
     */
    public function hasMetricVariable(): bool
    {
        return !empty($this->metric_variable_name) && !empty($this->metric_variable_value) && $this->metric_variable_value > 0;
    }
    
    /**
     * Get the metric variable display name
     */
    public function getMetricVariableDisplay(): string
    {
        if (!$this->hasMetricVariable()) {
            return 'N/A';
        }
        
        return $this->metric_variable_name . ' (' . number_format($this->metric_variable_value, 2) . ')';
    }
}