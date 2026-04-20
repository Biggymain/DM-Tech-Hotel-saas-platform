<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InvoicePaidBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $hotelId;
    public $branchId;
    public $invoiceData;

    /**
     * Create a new event instance.
     */
    public function __construct(int $hotelId, int $branchId, array $invoiceData)
    {
        $this->hotelId = $hotelId;
        $this->branchId = $branchId;
        $this->invoiceData = $invoiceData;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("hotel.{$this->hotelId}.branch.{$this->branchId}.billing"),
            new PresenceChannel("presence-hotel.{$this->hotelId}.branch.{$this->branchId}.staff")
        ];
    }
}
