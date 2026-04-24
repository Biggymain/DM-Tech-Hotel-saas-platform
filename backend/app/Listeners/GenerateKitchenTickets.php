<?php

namespace App\Listeners;

use App\Events\OrderClaimed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * GenerateKitchenTickets
 *
 * Responds to two events:
 *   - OrderClaimed  → Guest-portal QR orders: KDS ticket generated ONLY after a
 *                     waitress claims the order (two-step approval pipeline).
 *   - OrderCreated  → POS orders: KDS ticket generated immediately on order
 *                     creation (single-step POS pipeline).
 *
 * Items are grouped by department so each kitchen section receives its own ticket.
 * Idempotency: uses firstOrCreate to guard against double-dispatch scenarios.
 */
class GenerateKitchenTickets
{
    public function __construct() {}

    /**
     * Handle the event — accepts OrderClaimed.
     *
     * @param  OrderClaimed  $event
     */
    public function handle(OrderClaimed $event): void
    {
        $order = $event->order->load('items.menuItem');

        // Guard: if tickets already exist for this order, do not re-create them.
        // This makes the listener idempotent against double-dispatch scenarios.
        if (\App\Models\KitchenTicket::where('order_id', $order->id)->exists()) {
            return;
        }

        // Group items by their MenuItem's department_id, fallback to the order's department_id
        $groupedItems = $order->items->groupBy(function ($orderItem) use ($order) {
            return $orderItem->menuItem
                ? $orderItem->menuItem->department_id
                : $order->department_id;
        });

        foreach ($groupedItems as $departmentId => $items) {
            // Create one KitchenTicket per department (firstOrCreate for hard idempotency)
            $ticket = \App\Models\KitchenTicket::firstOrCreate(
                [
                    'order_id'      => $order->id,
                    'department_id' => $departmentId,
                ],
                [
                    'hotel_id'      => $order->hotel_id,
                    'branch_id'     => $order->outlet_id,
                    'outlet_id'     => $order->outlet_id,
                    'ticket_number' => 'KDS-' . $order->order_number . '-' . $departmentId,
                    'status'        => 'queued',
                ]
            );

            // Only create items if this is a freshly created ticket
            if ($ticket->wasRecentlyCreated) {
                foreach ($items as $index => $item) {
                    \App\Models\KitchenTicketItem::create([
                        'hotel_id'          => $order->hotel_id,
                        'kitchen_ticket_id' => $ticket->id,
                        'order_item_id'     => $item->id,
                        'menu_item_id'      => $item->menu_item_id,
                        'quantity'          => $item->quantity,
                        'notes'             => $item->notes,
                        'kitchen_section'   => $item->kitchen_section,
                        'display_order'     => $index,
                        'status'            => 'queued',
                    ]);
                }
            }
        }
    }
}
