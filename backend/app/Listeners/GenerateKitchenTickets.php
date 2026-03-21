<?php

namespace App\Listeners;

use App\Events\OrderCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class GenerateKitchenTickets
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OrderCreated $event): void
    {
        $order = $event->order->load('items.menuItem');

        // Group items by their MenuItem's department_id, fallback to order's default department_id
        $groupedItems = $order->items->groupBy(function($orderItem) use ($order) {
            return $orderItem->menuItem ? $orderItem->menuItem->department_id : $order->department_id;
        });

        foreach ($groupedItems as $departmentId => $items) {
            // Generate a KitchenTicket per department
            $ticket = \App\Models\KitchenTicket::create([
                'hotel_id' => $order->hotel_id,
                'order_id' => $order->id,
                'department_id' => $departmentId,
                'outlet_id' => $order->outlet_id,
                'ticket_number' => 'KDS-' . $order->order_number . '-' . $departmentId,
                'status' => 'queued',
            ]);
            
            // Generate KitchenTicketItems for each grouped item
            foreach($items as $index => $item) {
                \App\Models\KitchenTicketItem::create([
                    'hotel_id' => $order->hotel_id,
                    'kitchen_ticket_id' => $ticket->id,
                    'order_item_id' => $item->id,
                    'menu_item_id' => $item->menu_item_id,
                    'quantity' => $item->quantity,
                    'notes' => $item->notes,
                    'kitchen_section' => $item->kitchen_section,
                    'display_order' => $index,
                    'status' => 'queued',
                ]);
            }
        }
    }
}
