<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RatePlanChannelMap extends Model
{
    use \App\Traits\Tenantable;

    protected $fillable = ['hotel_id', 'rate_plan_id', 'ota_channel_id', 'external_rate_plan_id'];

    public function ratePlan() { return $this->belongsTo(RatePlan::class); }
    public function otaChannel() { return $this->belongsTo(OtaChannel::class); }
}
