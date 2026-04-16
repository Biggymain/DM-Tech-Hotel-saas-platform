<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeisureBundle extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'menu_item_id',
        'inventory_item_id',
        'quantity',
    ];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
