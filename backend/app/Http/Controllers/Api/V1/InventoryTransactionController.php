<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InventoryTransaction;
use App\Models\InventoryItem;
use Illuminate\Support\Facades\DB;

class InventoryTransactionController extends Controller
{
    public function index(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        $query = InventoryTransaction::where('hotel_id', $hotelId)->with('item');

        if ($request->has('inventory_item_id')) {
            $query->where('inventory_item_id', $request->inventory_item_id);
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $hotelId = $request->user()->hotel_id;

        $validated = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'type' => 'required|in:in,out,adjustment,transfer',
            'quantity' => 'required|numeric|min:0.01',
            'outlet_id' => 'nullable|exists:outlets,id',
            'source_outlet_id' => 'nullable|exists:outlets,id',
            'destination_outlet_id' => 'nullable|exists:outlets,id',
            'notes' => 'nullable|string'
        ]);

        $item = InventoryItem::where('hotel_id', $hotelId)->findOrFail($validated['inventory_item_id']);

        return DB::transaction(function () use ($validated, $item, $hotelId, $request) {
            $transaction = InventoryTransaction::create(array_merge($validated, [
                'hotel_id' => $hotelId,
                'created_by_user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
                'device' => $request->header('User-Agent')
            ]));

            if ($validated['type'] === 'in') {
                $item->current_stock += $validated['quantity'];
            } elseif ($validated['type'] === 'out') {
                $item->current_stock -= $validated['quantity'];
            } elseif ($validated['type'] === 'adjustment') {
                // For direct adjustment, let's treat quantity as an offset right now,
                // or require explicit signs. The requirement said just adjust based on logic.
                // We'll treat positive adjusting sum, if you need negative adjust, use out.
                $item->current_stock += $validated['quantity'];
            }

            $item->save();

            return response()->json($transaction, 201);
        });
    }

    public function show(Request $request, $id)
    {
        $transaction = InventoryTransaction::where('hotel_id', $request->user()->hotel_id)
            ->with(['item', 'sourceOutlet', 'destinationOutlet'])
            ->findOrFail($id);

        return response()->json($transaction);
    }
}
