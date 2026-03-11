<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuestPortalSession extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'hotel_id',
        'guest_id',
        'room_id',
        'reservation_id',
        'context_type',
        'context_id',
        'session_token',
        'pin_code',
        'device_info',
        'device_fingerprint',
        'trusted_device',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'trusted_device' => 'boolean',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
