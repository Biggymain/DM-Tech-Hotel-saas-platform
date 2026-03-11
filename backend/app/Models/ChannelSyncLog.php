<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Model;

class ChannelSyncLog extends Model
{
    use Tenantable;

    protected $fillable = [
        'hotel_id',
        'ota_channel_id',
        'operation',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function otaChannel()
    {
        return $this->belongsTo(OtaChannel::class);
    }
}
