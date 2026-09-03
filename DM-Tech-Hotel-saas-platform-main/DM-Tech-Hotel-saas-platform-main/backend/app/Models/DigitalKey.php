<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DigitalKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'reservation_id',
        'room_number',
        'provider',
        'key_code',
        'bluetooth_link',
        'qr_data',
        'status', // active, expired, revoked
        'valid_from',
        'valid_until',
        'provider_response',
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'provider_response' => 'array',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
