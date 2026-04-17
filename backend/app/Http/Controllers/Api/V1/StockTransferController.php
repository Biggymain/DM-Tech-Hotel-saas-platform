<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockTransfer;
use App\Models\InventoryItem;
use App\Models\StaffDailyPin;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class StockTransferController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private InventoryService $inventoryService) {}
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
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0',
        ]);

        if ($transfer->status !== 'requested') {
            return response()->json(['message' => 'Transfer already dispatched or received'], 422);
        }

        DB::transaction(function () use ($transfer, $validated, $request) {
            $transfer->update([
                'quantity_dispatched' => $validated['quantity'],
                'dispatched_by'       => $request->user()->id,
                'dispatched_at'       => now(),
                'status'              => 'dispatched',
            ]);

            // Deduct stock from the source immediately (Main Store liability release)
            $this->inventoryService->deductStock(
                $transfer->inventory_item_id,
                $validated['quantity'],
                get_class($transfer),
                $transfer->id
            );
        });

        return response()->json([
            'message' => 'Stock dispatched and liability shifted to transit.',
            'data'    => $transfer->fresh()
        ]);

        \App\Jobs\SyncToCloudJob::dispatch((int) $transfer->hotel_id)->afterCommit();
    }

    public function receive(Request $request, StockTransfer $transfer)
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0',
            'pin'      => 'required|string|size:4'
        ]);

        if ($transfer->status !== 'dispatched') {
            return response()->json(['message' => 'Transfer must be dispatched before it can be received'], 422);
        }

        // 4-Digit Daily PIN Handshake Verification
        $dailyPin = StaffDailyPin::where('user_id', $request->user()->id)
            ->where('expires_at', '>', now())
            ->first();

        if (!$dailyPin || !Hash::check($validated['pin'], $dailyPin->pin_hash)) {
            // Audit log for failed reception attempt
            \App\Models\AuditLog::create([
                'hotel_id' => $request->user()->hotel_id,
                'user_id' => $request->user()->id,
                'change_type' => 'STOCK_RECEIVE_FAILED_PIN',
                'entity_type' => get_class($transfer),
                'entity_id' => $transfer->id,
                'reason' => "Invalid Daily PIN attempt for stock reception (#{$transfer->id})",
            ]);
            return response()->json(['message' => 'Invalid or expired Daily PIN'], 403);
        }

        DB::transaction(function () use ($transfer, $validated, $request) {
            $transfer->update([
                'quantity_received' => $validated['quantity'],
                'received_by'       => $request->user()->id,
                'received_at'       => now(),
                'status'            => 'received',
            ]);

            // Liability Shift: Increase destination stock
            $sourceItem = $transfer->item;
            $destinationItem = $this->inventoryService->resolveItemForOutlet($sourceItem, $transfer->to_location_id);
            
            $destinationItem->increment('current_stock', $validated['quantity']);

            // High-Priority SIEM Log (Severity 10) for Chain of Custody
            \Illuminate\Support\Facades\Log::channel('siem')->info('Stock Received House-Handshake', [
                'severity_score' => 10,
                'staff_id'       => $request->user()->id,
                'hardware_hash'  => $request->header('X-Hardware-Id'),
                'correlation_ip' => $request->ip(),
                'transfer_id'    => $transfer->id,
                'sku'            => $destinationItem->sku,
                'outlet_id'      => $transfer->to_location_id,
            ]);

            \App\Models\InventoryTransaction::create([
                'hotel_id'          => $transfer->hotel_id,
                'outlet_id'         => $transfer->to_location_id,
                'inventory_item_id' => $destinationItem->id,
                'type'              => 'in',
                'quantity'          => $validated['quantity'],
                'reference_type'    => get_class($transfer),
                'reference_id'      => $transfer->id,
                'notes'             => "Stock Transfer Received via PIN Handshake (#{$transfer->id})",
                'created_by_user_id' => $request->user()->id,
            ]);
        });

        return response()->json([
            'message' => 'Stock received and sub-inventory updated.',
            'data'    => $transfer->fresh()
        ]);

        \App\Jobs\SyncToCloudJob::dispatch((int) $transfer->to_location_id)->afterCommit();
    }
}
