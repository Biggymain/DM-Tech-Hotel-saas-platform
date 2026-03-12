<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\DB;
use Exception;

class InventoryService
{
    /**
     * Deduct stock from an inventory item.
     * Must be called within a DB transaction for atomicity.
     */
    public function deductStock(int $itemId, float $quantity, string $sourceType, int $sourceId): void
    {
        $item = InventoryItem::lockForUpdate()->findOrFail($itemId);

        if ($item->current_stock < $quantity) {
            throw new Exception("Insufficient stock for item: {$item->name}. Remaining: {$item->current_stock}");
        }

        $item->current_stock -= $quantity;
        $item->save();

        InventoryTransaction::create([
            'hotel_id' => $item->hotel_id,
            'outlet_id' => $item->outlet_id,
            'inventory_item_id' => $item->id,
            'type' => 'deduction',
            'quantity' => $quantity,
            'reference_type' => $sourceType,
            'reference_id' => $sourceId,
            'notes' => "Automatic deduction for POS charge.",
            'created_by_user_id' => auth()->id() ?? null,
        ]);
    }
}
