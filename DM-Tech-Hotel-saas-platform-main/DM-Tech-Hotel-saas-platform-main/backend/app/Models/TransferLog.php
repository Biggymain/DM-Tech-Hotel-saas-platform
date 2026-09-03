<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransferLog extends Model
{
    protected $fillable = [
        'hotel_id',
        'order_item_id',
        'source_staff_id',
        'target_staff_id',
        'source_session_id',
        'target_session_id',
        'status',
        'reason',
        'failed_attempts',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function sourceStaff()
    {
        return $this->belongsTo(User::class, 'source_staff_id');
    }

    public function targetStaff()
    {
        return $this->belongsTo(User::class, 'target_staff_id');
    }

    public function sourceSession()
    {
        return $this->belongsTo(TableSession::class, 'source_session_id');
    }

    public function targetSession()
    {
        return $this->belongsTo(TableSession::class, 'target_session_id');
    }
}
