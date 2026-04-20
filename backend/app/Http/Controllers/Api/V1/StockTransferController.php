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

    public function __construct(private \App\Services\StockTransferService $transferService) {}

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
            'from_location_id'  => 'required|integer',
            'quantity'          => 'required|numeric|min:0.01',
        ]);

        $transfer = $this->transferService->initiateTransfer(
            $validated['inventory_item_id'],
            $validated['quantity'],
            $validated['from_location_id'],
            $validated['to_location_id'],
            $request->user()->id
        );

        return response()->json([
            'message' => 'Stock transfer initiated successfully',
            'data'    => $transfer->load('item')
        ], 201);
    }

    public function dispatch(Request $request, StockTransfer $transfer)
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0',
        ]);

        try {
            $transfer = $this->transferService->dispatchTransfer(
                $transfer->id,
                $request->user()->id,
                $validated['quantity']
            );

            return response()->json([
                'message' => 'Stock dispatched and liability shifted to transit.',
                'data'    => $transfer->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function receive(Request $request, StockTransfer $transfer)
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0',
            'pin'      => 'required|string|size:4'
        ]);

        try {
            $transfer = $this->transferService->acceptTransfer(
                $transfer->id,
                $request->user()->id,
                $validated['pin'],
                $validated['quantity']
            );

            return response()->json([
                'message' => $transfer->status === 'disputed' 
                    ? 'Stock received with DISPUTE. Manager notified.' 
                    : 'Stock received and sub-inventory updated.',
                'data'    => $transfer->fresh()
            ]);
        } catch (\Exception $e) {
            $code = $e->getCode() ?: 422;
            if ($code === 403) $code = 403;
            return response()->json(['message' => $e->getMessage()], $code);
        }
    }
}
