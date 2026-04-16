<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\DashboardCacheCleaner;

class Room extends BaseModel
{
    use HasFactory,  DashboardCacheCleaner, \App\Traits\Syncable;

    protected $fillable = [
        'hotel_id',
        'room_type_id',
        'room_number',
        'floor',
        'status',
        'housekeeping_status',
        'maintenance_notes',
        'maintenance_until',
        'last_cleaned_at',
        'last_inspected_at',
        'assigned_housekeeper_id',
    ];

    protected $casts = [
        'maintenance_until' => 'datetime',
        'last_cleaned_at' => 'datetime',
        'last_inspected_at' => 'datetime',
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

    public function assignedHousekeeper()
    {
        return $this->belongsTo(User::class, 'assigned_housekeeper_id');
    }

    public function housekeepingTasks()
    {
        return $this->hasMany(HousekeepingTask::class);
    }

    public function maintenanceRequests()
    {
        return $this->hasMany(MaintenanceRequest::class);
    }
}
