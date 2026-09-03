<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

use App\Models\MaintenanceRequest;

class MaintenanceRequested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $maintenanceRequest;

    public function __construct(MaintenanceRequest $request)
    {
        $this->maintenanceRequest = $request;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('hotel.' . $this->maintenanceRequest->hotel_id . '.maintenance'),
        ];
    }
    
    public function broadcastWith(): array
    {
        return [
            'request_id' => $this->maintenanceRequest->id,
            'room_id' => $this->maintenanceRequest->room_id,
            'issue_type' => $this->maintenanceRequest->issue_type,
            'priority' => $this->maintenanceRequest->priority,
            'message' => "New maintenance request for room {$this->maintenanceRequest->room->room_number}.",
        ];
    }
}
