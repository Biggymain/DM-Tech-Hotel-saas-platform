<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class Notification extends Model
{
    use Tenantable;

    protected $fillable = [
        'hotel_id',
        'outlet_id',
        'user_id',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }
}
