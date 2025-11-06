<?php
/**
 * eclectyc-energy/app/models/Tariff.php
 * Tariff model for database operations
 * Last updated: 06/11/2024 14:45:00
 */

namespace App\Models;

class Tariff extends BaseModel
{
    protected static string $table = 'tariffs';
    
    /**
     * Get supplier relationship
     */
    public function supplier(): ?Supplier
    {
        return $this->supplier_id ? Supplier::find($this->supplier_id) : null;
    }
    
    /**
     * Calculate cost for consumption
     */
    public function calculateCost(float $consumption, int $days = 1): float
    {
        $unitCost = ($this->unit_rate ?? 0) * $consumption;
        $standingCharge = ($this->standing_charge ?? 0) * $days;
        
        return ($unitCost + $standingCharge) / 100; // Convert pence to pounds
    }
    
    /**
     * Get active tariff for date
     */
    public static function getActiveForDate(string $date, string $energyType = 'electricity'): ?self
    {
        $db = \App\Config\Database::getConnection();
        if (!$db) return null;
        
        $stmt = $db->prepare("
            SELECT * FROM tariffs 
            WHERE energy_type = ?
            AND valid_from <= ?
            AND (valid_to IS NULL OR valid_to >= ?)
            AND is_active = TRUE
            ORDER BY valid_from DESC
            LIMIT 1
        ");
        
        $stmt->execute([$energyType, $date, $date]);
        
        $data = $stmt->fetch();
        
        return $data ? new static($data) : null;
    }
}