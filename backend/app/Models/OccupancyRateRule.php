<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class OccupancyRateRule extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'hotel_id',
        'rate_plan_id',
        'occupancy_threshold',
        'price_modifier_percentage',
    ];

    protected $casts = [
        'occupancy_threshold' => 'integer',
        'price_modifier_percentage' => 'decimal:2',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function ratePlan()
    {
        return $this->belongsTo(RatePlan::class);
    }
}
