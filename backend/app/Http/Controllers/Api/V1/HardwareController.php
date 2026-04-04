<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LeisureService;
use Illuminate\Http\Request;

class HardwareController extends Controller
{
    public function __construct(private LeisureService $leisureService) {}

    /**
     * GET /api/v1/hardware/verify/{code}
     * 
     * Verifies the "Triangle of Truth":
     * 1. Access Code (Membership OR QR OR Staff PIN)
     * 2. Inventory (Was the drink check handled?)
     * 3. Reservation (Is the guest active?)
     */
    public function verify(Request $request, $code)
    {
        // Hardware bridge usually provides its own ID to identify the section (Outlet)
        $outletId = $request->input('outlet_id');
        if (!$outletId) {
             return response()->json(['allow' => false, 'message' => 'Outlet configuration missing on bridge.'], 400);
        }

        $result = $this->leisureService->verifyAccess($code, $outletId);

        if ($result['allow']) {
            return response()->json([
                'allow' => true,
                'type' => $result['type'],
                'message' => 'Access Granted'
            ]);
        }

        return response()->json([
            'allow' => false,
            'message' => $result['message'] ?? 'Access Denied'
        ], 403);
    }
}
