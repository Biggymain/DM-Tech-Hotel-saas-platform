<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\GuestPortalService;
use App\Models\Room;
use App\Models\GuestPortalSession;
use App\Events\GuestPortalSessionCreated;

class GuestPortalController extends Controller
{
    protected $portalService;

    public function __construct(GuestPortalService $portalService)
    {
        $this->portalService = $portalService;
    }

    public function startSession(Request $request)
    {
        $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'device_info' => 'nullable|string',
        ]);

        $room = Room::findOrFail($request->room_id);

        $session = $this->portalService->createSessionFromQR($room, $request->device_info);

        event(new GuestPortalSessionCreated($session));

        return response()->json([
            'message' => 'Session created successfully.',
            'session_token' => $session->session_token,
            'requires_pin' => !empty($session->pin_code),
        ], 201);
    }

    public function authenticate(Request $request)
    {
        $request->validate([
            'session_token' => 'required|string',
            'pin' => 'nullable|string',
            'device_fingerprint' => 'nullable|string'
        ]);

        $session = $this->portalService->authenticateWithPin(
            $request->session_token,
            $request->pin,
            $request->device_fingerprint
        );

        return response()->json([
            'message' => 'Authentication successful.',
            'session' => $session->refresh()
        ]);
    }

    public function dashboard(Request $request)
    {
        $request->validate([
            'session_token' => 'required|string'
        ]);

        $session = GuestPortalSession::where('session_token', $request->session_token)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        // Must be trusted
        if (!$session->trusted_device && !empty($session->pin_code)) {
            return response()->json(['message' => 'Unauthenticated or PIN required.'], 401);
        }

        $dashboard = $this->portalService->getGuestDashboard($session);

        return response()->json($dashboard);
    }
}
