<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class OtaReservation extends Model
{
    

    protected $fillable = [
        'hotel_id', 'ota_channel_id', 'external_reservation_id', 'guest_name', 'check_in', 'check_out', 
        'room_type', 'total_price', 'status', 'raw_payload', 'reservation_id'
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'check_in' => 'date',
        'check_out' => 'date',
        'total_price' => 'decimal:2',
    ];

    public function otaChannel()
    {
        return $this->belongsTo(OtaChannel::class);
    }

    public function internalReservation()
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }
}
