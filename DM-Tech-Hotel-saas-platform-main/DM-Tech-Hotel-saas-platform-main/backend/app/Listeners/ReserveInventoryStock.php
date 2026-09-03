<?php

namespace App\Listeners;

use App\Events\OrderConfirmed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\InventoryItem;
use App\Exceptions\InsufficientStockException;
use Illuminate\Support\Facades\DB;

class ReserveInventoryStock
{
    public function handle(OrderConfirmed $event): void
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
                    $available = $inventoryItem->current_stock - $inventoryItem->reserved_stock;

                    if ($available < $totalNeeded) {
                        throw new InsufficientStockException("Not enough stock for {$inventoryItem->name}. Available: {$available}, Required: {$totalNeeded}");
                    }

                    $inventoryItem->reserved_stock += $totalNeeded;
                    $inventoryItem->save();
                }
            }
        });
    }
}
