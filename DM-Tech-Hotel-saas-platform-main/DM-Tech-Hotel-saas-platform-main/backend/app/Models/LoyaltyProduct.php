<?php

namespace App\Models;

class LoyaltyProduct extends BaseModel
{
    protected $fillable = [
        'hotel_id',
        'name',
        'type',
        'point_cost',
        'inventory_item_id',
        'is_active',
    ];

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
