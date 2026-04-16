<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Notification extends Model
{
    use HasUuids;

    protected $fillable = [
        'hotel_id',
        'outlet_id',
        'user_id',
        'notifiable_id',
        'notifiable_type',
        'type',
        'priority',
        'title',
        'message',
        'data',
        'is_read',
        'expires_at',
        'broadcasted_at'
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'expires_at' => 'datetime',
        'broadcasted_at' => 'datetime'
    ];

    public function notifiable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }
}
