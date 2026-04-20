<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HardwareDevice extends BaseModel
{
    protected $fillable = [
        'hotel_id',
        'branch_id',
        'device_name',
        'hardware_uuid',
        'zone_type',
        'is_verified',
        'status',
    ];

    /**
     * Scope to only verified devices if requested.
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }
}
