<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class Room extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'hotel_id',
        'room_type_id',
        'room_number',
        'floor',
        'status',
        'housekeeping_status',
        'maintenance_notes',
        'maintenance_until',
    ];

    protected $casts = [
        'maintenance_until' => 'datetime',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType()
    {
        return $this->belongsTo(RoomType::class);
    }
    
    public function reservations()
    {
        return $this->belongsToMany(Reservation::class, 'reservation_rooms')->withPivot('rate')->withTimestamps();
    }
}
