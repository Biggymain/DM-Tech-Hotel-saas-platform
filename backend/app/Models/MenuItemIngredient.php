<?php

namespace App\Models;


class MenuItemIngredient extends BaseModel
{
    protected $fillable = [
        'hotel_id', 'menu_item_id', 'inventory_item_id', 'quantity_required'
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
