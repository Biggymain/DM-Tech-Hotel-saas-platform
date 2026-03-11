<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;
use App\Traits\DashboardCacheCleaner;

class Order extends Model
{
    use Tenantable, DashboardCacheCleaner;

    protected $fillable = [
        'hotel_id',
        'outlet_id',
        'department_id',
        'room_id',
        'table_number',
        'order_number',
        'order_source',
        'status',
        'total_amount',
        'payment_status',
        'payment_method',
        'created_by',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(OrderStatusHistory::class);
    }
}
