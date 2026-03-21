<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoomPriceCalculated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomType;
    public $date;
    public $ratePlan;
    public $finalPrice;

    /**
     * Create a new event instance.
     */
    public function __construct($roomType, $date, $ratePlan, $finalPrice)
    {
        $this->roomType = $roomType;
        $this->date = $date;
        $this->ratePlan = $ratePlan;
        $this->finalPrice = $finalPrice;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
