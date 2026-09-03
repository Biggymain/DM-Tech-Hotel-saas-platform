<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomTypeChannelMap extends Model
{
    use \App\Traits\Tenantable;

    protected $fillable = ['hotel_id', 'room_type_id', 'ota_channel_id', 'external_room_type_id'];

    public function roomType() { return $this->belongsTo(RoomType::class); }
    public function otaChannel() { return $this->belongsTo(OtaChannel::class); }
}
