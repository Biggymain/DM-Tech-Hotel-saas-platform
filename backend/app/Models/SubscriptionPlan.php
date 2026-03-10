<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = ['name', 'slug', 'price', 'description', 'features'];

    protected $casts = [
        'features' => 'array',
        'price' => 'decimal:2',
    ];

    public function hotels()
    {
        return $this->hasMany(Hotel::class);
    }
}
