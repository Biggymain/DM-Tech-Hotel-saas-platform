<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuestPortalSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'guest_id',
        'room_id',
        'reservation_id',
        'context_type',
        'context_id',
        'context_data',
        'session_token',
        'pin_code',
        'device_info',
        'device_fingerprint',
        'trusted_device',
        'expires_at',
        'status',
        'waiter_id',
        'last_activity_at',
    ];

    protected $casts = [
        'trusted_device' => 'boolean',
        'expires_at' => 'datetime',
        'context_data' => 'array',
        'last_activity_at' => 'datetime',
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

    public function waiter()
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }
}
