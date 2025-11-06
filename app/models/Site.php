<?php
/**
 * eclectyc-energy/app/models/Site.php
 * Site model for database operations
 * Last updated: 06/11/2024 14:45:00
 */

namespace App\Models;

class Site extends BaseModel
{
    protected static string $table = 'sites';
    
    /**
     * Get company relationship
     */
    public function company(): ?Company
    {
        return Company::find($this->company_id);
    }
    
    /**
     * Get region relationship
     */
    public function region(): ?Region
    {
        return $this->region_id ? Region::find($this->region_id) : null;
    }
    
    /**
     * Get all meters for this site
     */
    public function meters(): array
    {
        return Meter::where('site_id', $this->id);
    }
    
    /**
     * Calculate total consumption
     */
    public function calculateTotalConsumption(string $startDate, string $endDate): float
    {
        $total = 0.0;
        
        foreach ($this->meters() as $meter) {
            $total += $meter->calculateConsumption($startDate, $endDate);
        }
        
        return $total;
    }
}