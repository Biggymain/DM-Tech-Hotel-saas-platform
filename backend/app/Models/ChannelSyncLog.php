<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelSyncLog extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'hotel_id',
        'channel_integration_id',
        'sync_type',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
        'synced_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'synced_at' => 'datetime',
    ];

    public function channelIntegration()
    {
        return $this->belongsTo(ChannelIntegration::class);
    }
}
