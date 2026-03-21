<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KitchenStatusUpdatedBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $hotelId;
    public $ticketData;

    /**
     * Create a new event instance.
     */
    public function __construct(int $hotelId, array $ticketData)
    {
        $this->hotelId = $hotelId;
        $this->ticketData = $ticketData;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("hotel.{$this->hotelId}.orders"),
            new PrivateChannel("hotel.{$this->hotelId}.kitchen"),
            new PresenceChannel("presence-hotel.{$this->hotelId}.staff")
        ];
    }
}
