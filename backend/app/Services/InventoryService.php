<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\DB;
use Exception;

class InventoryService
{
    /**
     * Resolve the specific InventoryItem for a given outlet based on a template item's SKU.
     * If it doesn't exist, create it.
     */
    public function resolveItemForOutlet(InventoryItem $template, int $outletId): InventoryItem
    {
        return InventoryItem::where('sku', $template->sku)
            ->where('outlet_id', $outletId)
            ->where('hotel_id', $template->hotel_id)
            ->firstOrCreate([
                'sku' => $template->sku,
                'outlet_id' => $outletId,
                'hotel_id' => $template->hotel_id,
            ], [
                'name' => $template->name,
                'category' => $template->category,
                'unit_of_measurement' => $template->unit_of_measurement,
                'minimum_stock_level' => 0,
                'current_stock' => 0,
                'cost_per_unit' => $template->cost_per_unit,
                'status' => 'active',
            ]);
    }

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
            'type' => 'out',
            'quantity' => $quantity,
            'reference_type' => $sourceType,
            'reference_id' => $sourceId,
            'notes' => "Automatic deduction for POS charge.",
            'created_by_user_id' => auth()->id() ?? null,
        ]);
    }
}
