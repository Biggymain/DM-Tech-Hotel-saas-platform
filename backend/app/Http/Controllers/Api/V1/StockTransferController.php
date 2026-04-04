<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockTransfer;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class StockTransferController extends Controller
{
    use AuthorizesRequests;
    public function index(Request $request)
    {
        $query = StockTransfer::with(['item', 'requestedBy', 'dispatchedBy', 'receivedBy'])
            ->where('hotel_id', $request->user()->hotel_id)
            ->orderByDesc('created_at');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->paginate(50));
    }

    public function request(Request $request)
    {
        $validated = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'to_location_id'    => 'required|integer',
            'quantity'          => 'required|numeric|min:0.01',
        ]);

        $transfer = StockTransfer::create([
            'hotel_id'           => $request->user()->hotel_id,
            'inventory_item_id'  => $validated['inventory_item_id'],
            'to_location_id'     => $validated['to_location_id'],
            'quantity_requested' => $validated['quantity'],
            'requested_by'       => $request->user()->id,
            'status'             => 'requested',
        ]);

        return response()->json([
            'message' => 'Stock requested successfully',
            'data'    => $transfer->load('item')
        ], 201);
    }

    public function dispatch(Request $request, StockTransfer $transfer)
    {
        $this->authorize('update', $transfer); // Storekeeper role usually

        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0',
        ]);

        if ($transfer->status !== 'requested') {
            return response()->json(['message' => 'Transfer already dispatched or received'], 422);
        }

        $transfer->update([
            'quantity_dispatched' => $validated['quantity'],
            'dispatched_by'       => $request->user()->id,
            'dispatched_at'       => now(),
            'status'              => 'dispatched',
        ]);

        return response()->json([
            'message' => 'Stock dispatched by storekeeper',
            'data'    => $transfer->fresh()
        ]);
    }

    public function receive(Request $request, StockTransfer $transfer)
    {
        $this->authorize('update', $transfer); // Outlet Supervisor role

        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0',
        ]);

        if ($transfer->status !== 'dispatched') {
            return response()->json(['message' => 'Transfer must be dispatched before it can be received'], 422);
        }

        DB::transaction(function () use ($transfer, $validated, $request) {
            $transfer->update([
                'quantity_received' => $validated['quantity'],
                'received_by'       => $request->user()->id,
                'received_at'       => now(),
                'status'            => 'received',
            ]);

            // Automatically update inventory levels at the receiving outlet/location
            // Note: This logic depends on how locations are mapped to inventory rooms
            // For now, we just log the transaction.
            // Update inventory current_stock
            $item = InventoryItem::findOrFail($transfer->inventory_item_id);
            $item->increment('current_stock', $validated['quantity']);

            \App\Models\InventoryTransaction::create([
                'hotel_id'          => $transfer->hotel_id,
                'inventory_item_id' => $transfer->inventory_item_id,
                'type'              => 'in',
                'quantity'          => $validated['quantity'],
                'reference_type'    => get_class($transfer),
                'reference_id'      => $transfer->id,
                'notes'             => "Stock Transfer Received (#{$transfer->id})",
                'created_by_user_id' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => 'Stock received and inventory updated',
            'data'    => $transfer->fresh()
        ]);
    }
}
