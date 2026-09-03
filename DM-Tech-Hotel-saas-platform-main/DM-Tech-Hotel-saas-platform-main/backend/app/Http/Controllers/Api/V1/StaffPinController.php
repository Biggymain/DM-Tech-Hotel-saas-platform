<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StaffDailyPin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StaffPinController extends Controller
{
    /**
     * POST /api/v1/auth/staff/set-pin
     * 
     * Staff sets their 4-digit PIN for the active shift (12 hours).
     */
    public function setPin(Request $request)
    {
        $validated = $request->validate([
            'pin' => 'required|string|size:4|regex:/^[0-9]+$/',
        ]);

        $user = $request->user();

        $dailyPin = StaffDailyPin::updateOrCreate(
            [
                'user_id' => $user->id,
                'hotel_id' => $user->hotel_id,
            ],
            [
                'pin_hash' => Hash::make($validated['pin']),
                'expires_at' => now()->addHours(12),
            ]
        );

        // Audit log for PIN creation (Digital Fortress requirement)
        \App\Models\AuditLog::create([
            'hotel_id' => $user->hotel_id,
            'user_id' => $user->id,
            'change_type' => 'STAFF_PIN_CREATED',
            'entity_type' => get_class($dailyPin),
            'entity_id' => $dailyPin->id,
            'reason' => "Staff member created/updated their Daily PIN (Expires: {$dailyPin->expires_at})",
        ]);

        return response()->json([
            'message' => 'Daily PIN set successfully. Valid for 12 hours.',
            'expires_at' => $dailyPin->expires_at->toIso8601String(),
        ]);
    }
}
