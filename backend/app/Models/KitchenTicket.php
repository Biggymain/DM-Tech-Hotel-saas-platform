<?php

namespace App\Models;

use App\Models\BaseModel;

class KitchenTicket extends BaseModel
{
    protected $fillable = [
        'hotel_id',
        'order_id',
        'department_id',
        'outlet_id',
        'ticket_number',
        'status',
        'priority',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function items()
    {
        return $this->hasMany(KitchenTicketItem::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(KitchenTicketStatusHistory::class);
    }
}
