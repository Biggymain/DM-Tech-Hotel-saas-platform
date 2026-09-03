<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * NewOrderClaimed — Real-Time Waitress Handshake
 *
 * Broadcasts to the POS channel so the OutletManager instantly sees
 * which waitress has taken ownership of a pending guest order.
 *
 * Channel: hotel.{hotel_id}.branch.{outlet_id}.pos
 */
class NewOrderClaimed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Order $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Broadcast on the hotel's POS private channel scoped to the outlet (branch).
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(
                'hotel.' . $this->order->hotel_id .
                '.branch.' . $this->order->outlet_id .
                '.pos'
            ),
        ];
    }

    /**
     * Custom broadcast event name consumed by the OutletManager JS listener.
     */
    public function broadcastAs(): string
    {
        return 'NewOrderClaimed';
    }

    /**
     * Data payload pushed to the channel.
     */
    public function broadcastWith(): array
    {
        return [
            'order_id'     => $this->order->id,
            'order_number' => $this->order->order_number,
            'waiter_id'    => $this->order->waiter_id,
            'claimed_at'   => $this->order->claimed_at,
            'outlet_id'    => $this->order->outlet_id,
        ];
    }
}
