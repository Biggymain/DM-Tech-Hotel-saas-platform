<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelEvent extends Model
{
    

    protected $fillable = [
        'hotel_id',
        'name',
        'type',
        'start_date',
        'end_date',
        'impact_level',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * Get the demand score boost based on impact level.
     */
    public function getImpactBoost(): int
    {
        return match ($this->impact_level) {
            'low' => 5,
            'medium' => 10,
            'high' => 15,
            'critical' => 25,
            default => 0,
        };
    }
}
