<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryUsageReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'outlet_id',
        'inventory_item_id',
        'report_date',
        'quantity_used',
        'cost_value',
    ];
    
    protected $casts = [
        'report_date' => 'date',
    ];

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }
}
