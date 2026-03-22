<?php

namespace App\Events;

use App\Models\KitchenTicket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KitchenTicketStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public KitchenTicket $ticket,
        public string $newStatus
    ) {
        $this->ticket->load(['order', 'kitchenStation']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $hotelId = $this->ticket->hotel_id;
        $branchId = $this->ticket->branch_id ?? $hotelId;
        $stationId = $this->ticket->kitchen_station_id;

        $channels = [
            // Channel for the specific station's KDS
            new PrivateChannel("hotel.{$hotelId}.branch.{$branchId}.station.{$stationId}"),
            // Channel for the branch-wide KDS overview
            new PrivateChannel("hotel.{$hotelId}.branch.{$branchId}.kds"),
        ];

        // If ready, notify the waitress who created the order
        if ($this->newStatus === 'ready' && $this->ticket->order?->created_by) {
            $channels[] = new PrivateChannel("hotel.{$hotelId}.waiter.{$this->ticket->order->created_by}");
        }

        return $channels;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'kitchen.ticket.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'order_number' => $this->ticket->order?->order_number,
            'table_number' => $this->ticket->order?->table_number,
            'status' => $this->newStatus,
            'station_id' => $this->ticket->kitchen_station_id,
            'station_name' => $this->ticket->kitchenStation?->name ?? 'Main',
            'updated_at' => now()->toISOString(),
        ];
    }
}
