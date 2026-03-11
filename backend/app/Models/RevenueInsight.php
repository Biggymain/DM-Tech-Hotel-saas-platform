<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueInsight extends Model
{
    use Tenantable;

    protected $fillable = [
        'hotel_id',
        'date',
        'occupancy_rate',
        'avg_daily_rate',
        'revpar',
        'demand_score',
        'recommended_rate_adjustment',
    ];

    protected $casts = [
        'date' => 'date',
        'occupancy_rate' => 'decimal:2',
        'avg_daily_rate' => 'decimal:2',
        'revpar' => 'decimal:2',
        'demand_score' => 'integer',
        'recommended_rate_adjustment' => 'array',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
