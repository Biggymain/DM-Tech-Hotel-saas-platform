<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'inventory_item_id',
        'from_location_id',
        'to_location_id',
        'quantity_requested',
        'quantity_dispatched',
        'quantity_received',
        'requested_by',
        'dispatched_by',
        'received_by',
        'status',
        'dispatched_at',
        'received_at',
    ];

    protected $casts = [
        'quantity_requested' => 'decimal:2',
        'quantity_dispatched' => 'decimal:2',
        'quantity_received' => 'decimal:2',
        'dispatched_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function item()
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function dispatchedBy()
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
