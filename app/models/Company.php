<?php
/**
 * eclectyc-energy/app/models/Company.php
 * Company model for database operations
 * Last updated: 06/11/2024 14:45:00
 */

namespace App\Models;

class Company extends BaseModel
{
    protected static string $table = 'companies';
    
    /**
     * Get all sites for this company
     */
    public function sites(): array
    {
        return Site::where('company_id', $this->id);
    }
}