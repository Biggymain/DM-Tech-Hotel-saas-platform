<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelRateMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'channel_integration_id',
        'rate_plan_id',
        'channel_rate_identifier',
    ];

    public function channelIntegration()
    {
        return $this->belongsTo(ChannelIntegration::class);
    }

    public function ratePlan()
    {
        return $this->belongsTo(RatePlan::class);
    }
}
