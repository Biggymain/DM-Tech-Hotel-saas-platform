<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;
    protected $fillable = [
        'name', 
        'slug', 
        'price', 
        'billing_cycle', 
        'max_rooms', 
        'max_staff', 
        'description', 
        'features', 
        'is_active'
    ];

    protected $casts = [
        'features' => 'array',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function hotels()
    {
        return $this->hasMany(Hotel::class);
    }
}
