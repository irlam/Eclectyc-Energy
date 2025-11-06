<?php
/**
 * eclectyc-energy/app/models/Region.php
 * Region model for database operations
 * Last updated: 06/11/2024 14:45:00
 */

namespace App\Models;

class Region extends BaseModel
{
    protected static string $table = 'regions';
    
    /**
     * Get all sites in this region
     */
    public function sites(): array
    {
        return Site::where('region_id', $this->id);
    }
}