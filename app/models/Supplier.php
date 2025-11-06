<?php
/**
 * eclectyc-energy/app/models/Supplier.php
 * Supplier model for database operations
 * Last updated: 06/11/2024 14:45:00
 */

namespace App\Models;

class Supplier extends BaseModel
{
    protected static string $table = 'suppliers';
    
    /**
     * Get all meters for this supplier
     */
    public function meters(): array
    {
        return Meter::where('supplier_id', $this->id);
    }
    
    /**
     * Get all tariffs for this supplier
     */
    public function tariffs(): array
    {
        return Tariff::where('supplier_id', $this->id);
    }
}