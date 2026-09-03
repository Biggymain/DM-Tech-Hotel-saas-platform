<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionTier extends Model
{
    protected $fillable = [
        'name',
        'price',
        'room_limit',
        'features',
    ];

    protected $casts = [
        'features' => 'array',
        'price' => 'decimal:2',
    ];

    public function hotels()
    {
        return $this->hasMany(Hotel::class, 'subscription_tier_id');
    }
}
