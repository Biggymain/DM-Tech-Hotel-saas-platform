<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use  SoftDeletes;

    protected $fillable = [
        'hotel_id', 'supplier_id', 'status', 'total_amount', 'ordered_at', 'received_at'
    ];

    protected $casts = [
        'ordered_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
