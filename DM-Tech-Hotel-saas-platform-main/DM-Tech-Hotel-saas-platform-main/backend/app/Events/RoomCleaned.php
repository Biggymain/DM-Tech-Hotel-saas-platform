<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

use App\Models\Room;

class RoomCleaned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $room;

    public function __construct(Room $room)
    {
        $this->room = $room;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('hotel.' . $this->room->hotel_id . '.frontdesk'),
        ];
    }
    
    public function broadcastWith(): array
    {
        return [
            'room_id' => $this->room->id,
            'room_number' => $this->room->room_number,
            'message' => 'Room ' . $this->room->room_number . ' is clean and ready.',
        ];
    }
}
