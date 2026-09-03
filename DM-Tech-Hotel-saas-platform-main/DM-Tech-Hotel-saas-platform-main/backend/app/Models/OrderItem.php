<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderItem extends BaseModel
{
    use HasFactory;
    protected $fillable = [
        'hotel_id',
        'order_id',
        'menu_item_id',
        'quantity',
        'price',
        'subtotal',
        'notes',
        'kitchen_section',
        'status',
        'waiter_id',
        'table_session_id',
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

    public function waiter()
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function tableSession()
    {
        return $this->belongsTo(TableSession::class);
    }
}
