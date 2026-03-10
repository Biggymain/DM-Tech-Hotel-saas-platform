<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class Guest extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'hotel_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'identification_type',
        'identification_number',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
    
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}
