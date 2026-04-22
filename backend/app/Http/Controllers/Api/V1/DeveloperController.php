<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\HardwareFingerprintService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeveloperController extends Controller
{
    protected HardwareFingerprintService $fingerprintService;

    public function __construct(HardwareFingerprintService $fingerprintService)
    {
        $this->fingerprintService = $fingerprintService;
    }

    /**
     * Register the current terminal as a Master device.
     * This creates/updates a record in the local hardware_devices table.
     */
    public function registerTerminal(Request $request)
    {
        try {
            // Automatic Hardware ID capture: Header takes priority, otherwise generate from system fingerprint
            $hash = $request->header('X-Hardware-Id') ?? $this->fingerprintService->generateHash();
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Fingerprint Capture Failed',
                'message' => $e->getMessage()
            ], 500);
        }

        \App\Models\HardwareDevice::updateOrCreate(
            ['hardware_hash' => $hash],
            [
                'hotel_id' => null, // Master terminals exist at system level
                'hardware_uuid' => 'PHOENIX-' . substr($hash, 0, 8),
                'device_name' => 'Developer Master Terminal (' . ($request->header('User-Agent') ?? 'Unknown') . ')',
                'access_level' => 'master',
                'status' => 'active',
                'is_verified' => true,
            ]
        );

        Log::info("Phoenix Master Marriage Successful: Developer Terminal sealed with hash {$hash}");

        return response()->json([
            'message' => 'Phoenix Master Marriage Successful',
            'hardware_hash' => $hash,
            'access_level' => 'master',
            'status' => 'sealed'
        ]);
    }
}
