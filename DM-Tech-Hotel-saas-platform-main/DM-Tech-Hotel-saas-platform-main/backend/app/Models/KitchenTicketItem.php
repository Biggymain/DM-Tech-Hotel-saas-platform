<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class KitchenTicketItem extends BaseModel
{
    

    protected $fillable = [
        'hotel_id',
        'kitchen_ticket_id',
        'order_item_id',
        'menu_item_id',
        'quantity',
        'notes',
        'status',
        'kitchen_section',
        'display_order',
    ];

    public function ticket()
    {
        return $this->belongsTo(KitchenTicket::class, 'kitchen_ticket_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}
