<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        return response()->json(PurchaseOrder::where('hotel_id', $hotelId)->with(['supplier', 'items'])->get());
    }

    public function store(Request $request)
    {
        $hotelId = $request->user()->hotel_id;

        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'items' => 'required|array|min:1',
            'items.*.inventory_item_id' => 'required|exists:inventory_items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.cost_per_unit' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($validated, $hotelId) {
            $totalAmount = 0;
            foreach ($validated['items'] as &$item) {
                $item['total_cost'] = $item['quantity'] * $item['cost_per_unit'];
                $totalAmount += $item['total_cost'];
            }

            $po = PurchaseOrder::create([
                'hotel_id' => $hotelId,
                'supplier_id' => $validated['supplier_id'],
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'ordered_at' => now()
            ]);

            foreach ($validated['items'] as $item) {
                $po->items()->create($item);
            }

            return response()->json($po->load('items'), 201);
        });
    }

    public function show(Request $request, $id)
    {
        $po = PurchaseOrder::where('hotel_id', $request->user()->hotel_id)->with(['supplier', 'items.inventoryItem'])->findOrFail($id);
        return response()->json($po);
    }

    public function receive(Request $request, $id)
    {
        $hotelId = $request->user()->hotel_id;
        $po = PurchaseOrder::where('hotel_id', $hotelId)->with('items.inventoryItem')->findOrFail($id);

        if ($po->status === 'received') {
            return response()->json(['message' => 'Purchase order already received'], 400);
        }

        return DB::transaction(function () use ($po, $hotelId, $request) {
            $po->update([
                'status' => 'received',
                'received_at' => now()
            ]);

            foreach ($po->items as $item) {
                $inventoryItem = $item->inventoryItem;
                $inventoryItem->current_stock += $item->quantity;
                $inventoryItem->save();

                InventoryTransaction::create([
                    'hotel_id' => $hotelId,
                    'inventory_item_id' => $inventoryItem->id,
                    'type' => 'in',
                    'quantity' => $item->quantity,
                    'reference_type' => 'purchase',
                    'reference_id' => $po->id,
                    'notes' => 'Received from PO #' . $po->id,
                    'created_by_user_id' => $request->user()->id,
                ]);
            }

            return response()->json(['message' => 'Purchase order received successfully', 'po' => $po]);
        });
    }
}
