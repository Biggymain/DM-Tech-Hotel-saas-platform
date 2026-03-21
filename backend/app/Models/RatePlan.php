<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class RatePlan extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'hotel_id',
        'name',
        'description',
        'pricing_strategy',
        'base_price_modifier',
        'is_active',
        'valid_from',
        'valid_until',
        'min_price',
        'max_price',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'base_price_modifier' => 'decimal:2',
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomTypes()
    {
        return $this->belongsToMany(RoomType::class, 'room_type_rate_plan')->withPivot('base_price')->withTimestamps();
    }

    public function seasonalRates()
    {
        return $this->hasMany(SeasonalRate::class);
    }

    public function occupancyRules()
    {
        return $this->hasMany(OccupancyRateRule::class);
    }
}
