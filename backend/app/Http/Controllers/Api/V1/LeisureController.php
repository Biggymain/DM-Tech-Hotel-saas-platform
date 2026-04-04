<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\Outlet;
use App\Models\User;
use App\Services\LeisureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeisureController extends Controller
{
    public function __construct(private LeisureService $leisureService) {}

    /**
     * POST /api/v1/leisure/provision
     * 
     * Link hardware bridge and set inventory source.
     */
    public function provision(Request $request)
    {
        $validated = $request->validate([
            'outlet_id' => 'required|exists:outlets,id',
            'hardware_bridge_id' => 'required|string',
            'inventory_source_outlet_id' => 'required|exists:outlets,id', // Storekeeper source
            'supervisor_id' => 'nullable|exists:users,id',
        ]);

        $outlet = Outlet::findOrFail($validated['outlet_id']);
        
        // Use the existing modular metadata or settings system
        $outlet->update([
            'metadata' => array_merge($outlet->metadata ?? [], [
                'leisure_enabled' => true,
                'hardware_bridge_id' => $validated['hardware_bridge_id'],
                'inventory_source_id' => $validated['inventory_source_outlet_id'],
                'supervisor_id' => $validated['supervisor_id'],
            ])
        ]);

        return response()->json(['message' => "Leisure Module provisioned for {$outlet->name}."]);
    }

    /**
     * POST /api/v1/leisure/reset-credential
     * 
     * Manager verifies physical ID and issues a temporary daily PIN.
     */
    public function resetCredential(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'manager_verification' => 'required|accepted', // Manager confirmed physical ID
        ]);

        $pin = $this->leisureService->generateDailyPin($validated['user_id']);

        return response()->json([
            'message' => 'New temporary PIN issued.',
            'daily_pin' => $pin,
        ]);
    }

    /**
     * POST /api/v1/leisure/daily-pin
     * 
     * Generate or Get the staff's daily PIN for shared terminals.
     */
    public function generatePin(Request $request)
    {
        $pin = $this->leisureService->generateDailyPin($request->user()->id);
        return response()->json(['daily_pin' => $pin]);
    }

    /**
     * Membership API
     */
    public function index(Request $request)
    {
        $memberships = Membership::with('user')
            ->where('hotel_id', $request->user()->hotel_id)
            ->get();
        return response()->json(['data' => $memberships]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|string|in:daily,weekly,monthly,yearly',
            'price_paid' => 'required|numeric',
            'starts_at' => 'required|date',
            'expires_at' => 'required|date',
        ]);

        $membership = Membership::create(array_merge($validated, [
            'hotel_id' => $request->user()->hotel_id,
            'status' => 'active'
        ]));

        return response()->json(['message' => 'Membership created.', 'data' => $membership], 201);
    }

    public function show(Membership $membership)
    {
        return response()->json(['data' => $membership->load('user')]);
    }

    /**
     * GET /api/v1/admin/leisure/audit
     * 
     * Identify mismatches between facility entries and inventory transactions.
     */
    public function audit(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        
        $logs = \App\Models\LeisureAccessLog::with(['user', 'outlet'])
            ->whereHas('outlet', function($q) use ($hotelId) {
                $q->where('hotel_id', $hotelId);
            })
            ->latest()
            ->take(50)
            ->get()
            ->map(function ($log) {
                $status = 'VALID';
                $severity = 'LOW';
                $message = 'Access verified.';

                if ($log->method === 'QR' || $log->method === 'PASS') {
                    // Check if there is a corresponding inventory deduction for this user/outlet
                    $hasDeduction = \App\Models\InventoryTransaction::where('hotel_id', $log->outlet->hotel_id)
                        ->where('reference_type', 'App\Models\Order')
                        ->where('created_at', '>=', $log->entry_time->subMinutes(60))
                        ->where('created_at', '<=', $log->entry_time->addMinutes(10))
                        ->exists();
                    
                    if (!$hasDeduction && $log->allow) {
                        $status = 'MISMATCH';
                        $severity = 'HIGH';
                        $message = 'No drink deduction linked to this entry.';
                    }
                }

                if (!$log->allow) {
                    $status = 'DENIED';
                    $severity = 'MEDIUM';
                    $message = 'Hardware blocked access.';
                }

                return [
                    'id' => $log->id,
                    'type' => $log->method,
                    'user' => $log->user ? $log->user->name : 'Anonymous/Pass',
                    'status' => $status,
                    'message' => $message,
                    'time' => $log->entry_time->format('H:i A'),
                    'severity' => $severity
                ];
            });

        return response()->json(['data' => $logs]);
    }
}
