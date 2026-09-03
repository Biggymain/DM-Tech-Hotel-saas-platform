<?php

namespace App\Listeners;

use App\Events\OrderServed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\DB;

class DeductInventoryStock
{
    public function handle(OrderServed $event): void
    {
        $order = $event->order;
        $order->loadMissing('items.menuItem.ingredients');

        // Idempotency: prevent double deduction on replay
        if (InventoryTransaction::where('reference_type', 'order')
            ->where('reference_id', $order->id)
            ->where('type', 'out')
            ->exists()) {
            return;
        }

        DB::transaction(function () use ($order) {
            foreach ($order->items as $orderItem) {
                $menuItem = $orderItem->menuItem;
                if (!$menuItem) continue;

                foreach ($menuItem->ingredients as $ingredient) {
                    $inventoryItem = InventoryItem::lockForUpdate()->find($ingredient->inventory_item_id);
                    if (!$inventoryItem) continue;

                    $totalNeeded = $ingredient->quantity_required * $orderItem->quantity;

                    // Deduct from both current and reserved
                    $inventoryItem->reserved_stock = max(0, $inventoryItem->reserved_stock - $totalNeeded);
                    $inventoryItem->current_stock -= $totalNeeded;
                    $inventoryItem->save();

                    // Create transaction auditing ledger
                    InventoryTransaction::create([
                        'hotel_id' => $inventoryItem->hotel_id,
                        'outlet_id' => $inventoryItem->outlet_id,
                        'inventory_item_id' => $inventoryItem->id,
                        'type' => 'out',
                        'quantity' => $totalNeeded,
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'notes' => "Deducted for Order #{$order->order_number}"
                    ]);
                }
            }
        });
    }
}
