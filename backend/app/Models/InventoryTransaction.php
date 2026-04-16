<?php

namespace App\Models;


class InventoryTransaction extends BaseModel
{
    

    protected $fillable = [
        'hotel_id', 'outlet_id', 'inventory_item_id', 'type', 'quantity', 'reference_type',
        'reference_id', 'source_outlet_id', 'destination_outlet_id', 'ip_address', 'device',
        'notes', 'created_by_user_id'
    ];

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
