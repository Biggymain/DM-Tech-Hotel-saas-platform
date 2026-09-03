<?php

namespace App\Models;

class LoyaltyTransaction extends BaseModel
{
    protected $fillable = [
        'hotel_id',
        'guest_id',
        'outlet_id',
        'type',
        'points',
        'reference_type',
        'reference_id',
        'reason',
        'processed_by_id',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by_id');
    }
}
