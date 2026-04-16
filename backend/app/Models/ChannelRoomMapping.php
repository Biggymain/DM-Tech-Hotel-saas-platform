<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelRoomMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'channel_integration_id',
        'room_type_id',
        'channel_room_identifier',
    ];

    public function channelIntegration()
    {
        return $this->belongsTo(ChannelIntegration::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }
}
