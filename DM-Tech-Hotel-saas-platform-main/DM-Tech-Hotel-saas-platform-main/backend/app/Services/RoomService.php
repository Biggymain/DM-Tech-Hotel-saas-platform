<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomType;
use App\Events\RoomStatusChanged;
use Illuminate\Support\Facades\DB;

class RoomService
{
    /**
     * Create a new room type.
     */
    public function createRoomType(array $data)
    {
        return RoomType::create($data);
    }

    /**
     * Create a new room.
     */
    public function createRoom(array $data)
    {
        return Room::create($data);
    }

    /**
     * Update room status (available, occupied, maintenance, out_of_order).
     */
    public function updateRoomStatus(Room $room, string $status, ?string $maintenanceNotes = null, ?string $maintenanceUntil = null)
    {
        $room->status = $status;
        
        if ($status === 'maintenance') {
            $room->maintenance_notes = $maintenanceNotes;
            $room->maintenance_until = $maintenanceUntil;
        } else {
            $room->maintenance_notes = null;
            $room->maintenance_until = null;
        }

        $room->save();

        event(new RoomStatusChanged($room));

        return $room;
    }

    /**
     * Update housekeeping status (clean, dirty, cleaning, inspecting).
     */
    public function updateHousekeepingStatus(Room $room, string $status)
    {
        $room->housekeeping_status = $status;
        $room->save();

        event(new RoomStatusChanged($room));

        return $room;
    }
}
