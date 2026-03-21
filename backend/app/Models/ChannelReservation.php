<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelReservation extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'hotel_id',
        'channel_integration_id',
        'reservation_id',
        'channel_reservation_id',
        'raw_payload',
        'imported_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'imported_at' => 'datetime',
    ];

    public function channelIntegration()
    {
        return $this->belongsTo(ChannelIntegration::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
