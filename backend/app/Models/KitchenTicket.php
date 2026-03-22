<?php

namespace App\Models;

use App\Models\BaseModel;

class KitchenTicket extends BaseModel
{
    use \App\Traits\Syncable;

    protected $fillable = [
        'hotel_id',
        'branch_id',
        'order_id',
        'department_id',
        'outlet_id',
        'kitchen_station_id',
        'ticket_number',
        'status',
        'priority',
        'notes',
        'fired_at',
        'started_at',
        'completed_at',
    ];

    protected $appends = ['is_late'];

    public function getIsLateAttribute(): bool
    {
        if ($this->status === 'ready' || $this->status === 'served') {
            return false;
        }

        return $this->created_at->diffInMinutes(now()) > 20;
    }

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function kitchenStation()
    {
        return $this->belongsTo(KitchenStation::class);
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
