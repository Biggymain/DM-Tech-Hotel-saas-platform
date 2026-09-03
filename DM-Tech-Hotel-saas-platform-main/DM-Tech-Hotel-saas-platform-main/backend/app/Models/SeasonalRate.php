<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeasonalRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'rate_plan_id',
        'start_date',
        'end_date',
        'price_modifier',
        'days_of_week',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'price_modifier' => 'decimal:2',
        'days_of_week' => 'array',
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
