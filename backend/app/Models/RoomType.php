<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class RoomType extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'hotel_id',
        'name',
        'description',
        'base_price',
        'capacity',
        'amenities',
        'is_public',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'capacity' => 'integer',
        'amenities' => 'array',
        'is_public' => 'boolean',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
    
    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function ratePlans()
    {
        return $this->belongsToMany(RatePlan::class, 'room_type_rate_plan')->withPivot('base_price')->withTimestamps();
    }
}
