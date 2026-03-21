<?php

namespace App\Listeners;

use App\Events\OrderCancelled;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\InventoryItem;
use Illuminate\Support\Facades\DB;

class ReleaseInventoryStock
{
    public function handle(OrderCancelled $event): void
    {
        $order = $event->order;
        $order->loadMissing('items.menuItem.ingredients');

        DB::transaction(function () use ($order) {
            foreach ($order->items as $orderItem) {
                $menuItem = $orderItem->menuItem;
                if (!$menuItem) continue;

                foreach ($menuItem->ingredients as $ingredient) {
                    $inventoryItem = InventoryItem::lockForUpdate()->find($ingredient->inventory_item_id);
                    if (!$inventoryItem) continue;

                    $totalNeeded = $ingredient->quantity_required * $orderItem->quantity;
                    $inventoryItem->reserved_stock = max(0, $inventoryItem->reserved_stock - $totalNeeded);
                    $inventoryItem->save();
                }
            }
        });
    }
}
