<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'menu_item_id',
        'quantity',
        'price',
        'subtotal',
        'notes',
        'kitchen_section',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::creating(function ($item) {
            if (empty($item->subtotal) && !empty($item->price) && !empty($item->quantity)) {
                $item->subtotal = $item->price * $item->quantity;
            }
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}
