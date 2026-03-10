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
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'capacity' => 'integer',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
    
    public function rooms()
    {
        return $this->hasMany(Room::class);
    }
}
