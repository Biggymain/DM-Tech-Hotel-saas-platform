<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\HardwareFingerprintService;

class LicensingController extends Controller
{
    protected $fingerprintService;

    public function __construct(HardwareFingerprintService $fingerprintService)
    {
        $this->fingerprintService = $fingerprintService;
    }

    /**
     * POST /api/v1/activate-branch
     * Marries the current hardware fingerprint to a branch token.
     */
    public function activate(Request $request)
    {
        $request->validate([
            'branch_token' => 'required|uuid',
        ]);

        $branch = DB::connection('supabase')->table('branches')
            ->where('branch_token', $request->branch_token)
            ->first();

        if (!$branch) {
            return response()->json(['message' => 'Invalid branch token.'], 404);
        }

        // 7.5: Slot Enforcement
        $hotel = \App\Models\Hotel::find($branch->id);
        $slots = $hotel ? $hotel->device_slots : 5;
        
        $activeCount = DB::connection('supabase')->table('devices')
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->count();
            
        if ($activeCount >= $slots) {
            return response()->json([
                'message' => 'Branch device limit reached.',
                'slots' => $slots,
                'active_count' => $activeCount
            ], 403);
        }

        $hardwareHash = $request->hardware_id ?? $this->fingerprintService->generateHash();

        // Register or Reactivate the device
        DB::connection('supabase')->table('devices')->updateOrInsert(
            ['hardware_hash' => $hardwareHash],
            [
                'branch_id' => $branch->id,
                'is_active' => true,
                'last_sync' => now(),
                'updated_at' => now(),
            ]
        );

        // Clear licensing cache to force re-check
        Cache::forget("licensing_sentry_{$hardwareHash}");

        return response()->json([
            'message' => 'Branch successfully activated on this device.',
            'hardware_hash' => $hardwareHash,
            'branch_id' => $branch->id,
            'expires_at' => $branch->expires_at,
        ]);
    }
}
