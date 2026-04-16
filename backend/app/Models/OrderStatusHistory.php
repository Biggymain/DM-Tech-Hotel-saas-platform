<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class OrderStatusHistory extends Model
{
    

    protected $fillable = [
        'hotel_id',
        'order_id',
        'previous_status',
        'new_status',
        'changed_by',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
