<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * OrderFiredToStation
 *
 * Broadcast when a waiter fires an order (draft → pending).
 * The event is sent to the specific station channel(s) based on
 * the station_name field of each OrderItem's MenuItem.
 *
 * Channel format:
 *   private-hotel.{hotel_id}.station.{station_name}
 *   e.g.  private-hotel.1.station.grill
 *         private-hotel.1.station.bar
 *
 * This means only the Grill tablet rings when Grilled Fish is ordered,
 * and only the Bar tablet rings when cocktails arrive.
 */
class OrderFiredToStation implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly string $stationName,
        public readonly array $stationItems,  // Items belonging to THIS station only
    ) {}

    /**
     * Targeted private channel for this station within this hotel.
     * Chefs/bartenders subscribe to their own hotel+station channel.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("hotel.{$this->order->hotel_id}.branch.{$this->order->outlet_id}.station.{$this->stationName}");
    }

    public function broadcastAs(): string
    {
        return 'order.fired';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id'      => $this->order->id,
            'order_number'  => $this->order->order_number,
            'table_number'  => $this->order->table_number,
            'room_id'       => $this->order->room_id,
            'waiter_id'     => $this->order->waiter_id,
            'station'       => $this->stationName,
            'items'         => $this->stationItems,
            'fired_at'      => now()->toISOString(),
            'order_status'  => 'pending',
        ];
    }
}
