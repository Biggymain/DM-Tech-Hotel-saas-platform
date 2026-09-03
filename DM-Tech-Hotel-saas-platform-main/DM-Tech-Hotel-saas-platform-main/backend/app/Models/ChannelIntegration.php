<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'channel_name',
        'display_name',
        'api_key',
        'api_secret',
        'endpoint_url',
        'webhook_secret',
        'is_active',
        'sync_enabled',
        'sync_pricing',
        'sync_inventory',
        'sync_reservations',
        'last_sync_at',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'api_secret' => 'encrypted',
        'is_active' => 'boolean',
        'sync_enabled' => 'boolean',
        'sync_pricing' => 'boolean',
        'sync_inventory' => 'boolean',
        'sync_reservations' => 'boolean',
        'last_sync_at' => 'datetime',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomMappings()
    {
        return $this->hasMany(ChannelRoomMapping::class);
    }

    public function rateMappings()
    {
        return $this->hasMany(ChannelRateMapping::class);
    }

    public function reservations()
    {
        return $this->hasMany(ChannelReservation::class);
    }

    public function syncLogs()
    {
        return $this->hasMany(ChannelSyncLog::class);
    }
}
