<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Traits\DashboardCacheCleaner;

class Order extends BaseModel
{
    use DashboardCacheCleaner;

    protected static function booted()
    {
        $createSyncRecord = function ($order, $action) {
            if (empty($order->uuid) && $action === 'created') {
                $order->uuid = (string) \Illuminate\Support\Str::uuid();
                $order->saveQuietly();
            }

            $sync = \App\Models\SyncQueue::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'model_type' => get_class($order),
                'model_uuid' => $order->uuid ?? null,
                'action' => $action,
                'payload' => [
                    'uuid' => $order->uuid,
                    'model' => get_class($order),
                    'data' => clone $order,
                    'action' => $action,
                    'device_timestamp' => now()->toIso8601String()
                ],
                'status' => 'pending'
            ]);

            if (\App\Helpers\InternetConnection::isConnected()) {
                \App\Jobs\SyncOrderToCloudJob::dispatch($sync->id);
            }
        };

        static::created(fn($order) => $createSyncRecord($order, 'created'));
        static::updated(fn($order) => $createSyncRecord($order, 'updated'));
        static::deleted(fn($order) => $createSyncRecord($order, 'deleted'));
    }

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
