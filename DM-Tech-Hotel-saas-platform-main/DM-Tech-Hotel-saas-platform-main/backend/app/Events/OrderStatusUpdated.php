<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

/**
 * OrderStatusUpdated
 *
 * Broadcast to two channels simultaneously:
 *  1. The station channel — so the KDS tablet receives the status change
 *  2. The waiter's personal channel — so the waiter hears the "Order Ready" ping
 *
 * Channel examples:
 *   private-hotel.{hotel_id}.station.grill    → chef marks cooking/ready
 *   private-hotel.{hotel_id}.waiter.{waiter_id} → waiter sees their order status
 */
class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly Order $order,
        public readonly string $newStatus,
        public readonly string $stationName,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("hotel.{$this->order->hotel_id}.station.{$this->stationName}"),
        ];

        // Also notify the waiter who placed this order when it's ready
        if ($this->newStatus === 'ready' && $this->order->waiter_id) {
            $channels[] = new PrivateChannel("hotel.{$this->order->hotel_id}.waiter.{$this->order->waiter_id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'order.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id'     => $this->order->id,
            'order_number' => $this->order->order_number,
            'table_number' => $this->order->table_number,
            'room_id'      => $this->order->room_id,
            'station'      => $this->stationName,
            'status'       => $this->newStatus,
            'updated_at'   => now()->toISOString(),
        ];
    }
}
