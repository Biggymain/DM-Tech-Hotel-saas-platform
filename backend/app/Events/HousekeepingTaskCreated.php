<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

use App\Models\HousekeepingTask;

class HousekeepingTaskCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $task;

    public function __construct(HousekeepingTask $task)
    {
        $this->task = $task;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('hotel.' . $this->task->hotel_id . '.housekeeping'),
        ];
    }
    
    public function broadcastWith(): array
    {
        return [
            'task_id' => $this->task->id,
            'room_id' => $this->task->room_id,
            'type' => $this->task->task_type,
            'message' => "New housekeeping task created for room {$this->task->room->room_number}.",
        ];
    }
}
