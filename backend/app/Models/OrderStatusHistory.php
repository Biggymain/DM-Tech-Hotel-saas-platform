<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\Tenantable;

class OrderStatusHistory extends Model
{
    use Tenantable;

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
