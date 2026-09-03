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

class SlaThresholdExceeded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public KitchenTicket $ticket)
    {
        $this->ticket->load(['kitchenStation', 'order']);
    }

    public function broadcastOn(): array
    {
        $hotelId = $this->ticket->hotel_id;
        $branchId = $this->ticket->branch_id ?? $hotelId;

        return [
            // Notify Branch Managers
            new PrivateChannel("hotel.{$hotelId}.branch.{$branchId}.admin"),
            // Notify specific station managers
            new PrivateChannel("hotel.{$hotelId}.branch.{$branchId}.station.{$this->ticket->kitchen_station_id}.admin"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'sla.threshold.exceeded';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'ticket_number' => $this->ticket->ticket_number,
            'station_name' => $this->ticket->kitchenStation?->name ?? 'Main Kitchen',
            'order_number' => $this->ticket->order?->order_number,
            'table_number' => $this->ticket->order?->table_number,
            'elapsed_minutes' => $this->ticket->created_at->diffInMinutes(now()),
        ];
    }
}
