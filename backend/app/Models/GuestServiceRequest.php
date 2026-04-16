<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\DashboardCacheCleaner;

class GuestServiceRequest extends Model
{
    use HasFactory,  DashboardCacheCleaner;

    protected $fillable = [
        'hotel_id',
        'guest_id',
        'room_id',
        'reservation_id',
        'request_type',
        'description',
        'status',
        'assigned_to',
    ];

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
