<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InventoryItem extends BaseModel
{
    use HasFactory,  SoftDeletes;

    protected $fillable = [
        'hotel_id', 'outlet_id', 'name', 'sku', 'category', 'unit_of_measurement',
        'minimum_stock_level', 'current_stock', 'reserved_stock', 'cost_per_unit', 'status'
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }
}
