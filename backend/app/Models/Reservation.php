<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class Reservation extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'hotel_id',
        'guest_id',
        'reservation_number',
        'check_in_date',
        'check_out_date',
        'status',
        'source',
        'total_amount',
        'adults',
        'children',
        'special_requests',
    ];

    protected $casts = [
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'total_amount' => 'decimal:2',
        'adults' => 'integer',
        'children' => 'integer',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }
    
    public function rooms()
    {
        return $this->belongsToMany(Room::class, 'reservation_rooms')->withPivot('rate')->withTimestamps();
    }
    
    public function folios()
    {
        return $this->hasMany(Folio::class);
    }
}
