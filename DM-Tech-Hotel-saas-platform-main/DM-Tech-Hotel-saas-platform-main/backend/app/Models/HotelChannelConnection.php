<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class HotelChannelConnection extends Model
{
    

    protected $fillable = [
        'hotel_id', 'ota_channel_id', 'api_key', 'api_secret', 'refresh_token', 'status', 'last_sync_at'
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'api_secret' => 'encrypted',
        'refresh_token' => 'encrypted',
        'last_sync_at' => 'datetime',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function otaChannel()
    {
        return $this->belongsTo(OtaChannel::class);
    }
}
