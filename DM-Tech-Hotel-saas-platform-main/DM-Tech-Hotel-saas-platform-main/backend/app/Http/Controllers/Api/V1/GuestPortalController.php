<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use Illuminate\Http\Request;
use App\Services\GuestPortalService;
use App\Models\Room;
use App\Models\GuestPortalSession;
use App\Events\GuestPortalSessionCreated;
use App\Services\QrSignatureService;

class GuestPortalController extends Controller
{
    protected $portalService;
    protected $qrSignatureService;

    public function __construct(GuestPortalService $portalService, QrSignatureService $qrSignatureService)
    {
        $this->portalService = $portalService;
        $this->qrSignatureService = $qrSignatureService;
    }

    public function startSession(Request $request)
    {
        // 1. Security Hardening: Explicit 403 check for signature parameters (bypass default 422 validator)
        if (!$request->has(['signature', 'hotel_id', 'context_type', 'context_id'])) {
            return response()->json(['message' => 'Security validation failed: Missing QR signature parameters.'], 403);
        }

        $payloadToVerify = $request->only(['hotel_id', 'context_type', 'context_id']);
        if (!$this->qrSignatureService->validateSignature($payloadToVerify, (string)$request->signature)) {
            return response()->json(['message' => 'Invalid or tampered QR signature.'], 403);
        }

        // 2. Normal validation for data integrity and existence
        $request->validate([
            'hotel_id' => 'required|exists:hotels,id',
            'context_type' => 'required|in:room,outlet,table',
            'context_id' => 'required|integer',
            'context_data' => 'nullable|array',
            'device_info' => 'nullable|string',
        ]);

        $hotel = \App\Models\Hotel::with('activeSubscription')->find($request->hotel_id);
        if ($hotel && $hotel->activeSubscription && $hotel->activeSubscription->status === 'suspended') {
            return response()->json([
                'message' => 'Account Suspended: Please renew your subscription to perform this action.',
            ], 403);
        }

        $session = $this->portalService->createSessionFromContext(
            $request->hotel_id,
            $request->context_type,
            $request->context_id,
            $request->device_info,
            $request->context_data
        );

        event(new GuestPortalSessionCreated($session));

        return response()->json([
            'message' => 'Session created successfully.',
            'session_token' => $session->session_token,
            'requires_pin' => !empty($session->pin_code),
            'context_type' => $session->context_type
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
            ->where('status', '!=', 'revoked')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        // Must be trusted
        if (!$session->trusted_device && !empty($session->pin_code)) {
            return response()->json(['message' => 'Unauthenticated or PIN required.'], 401);
        }

        $dashboard = $this->portalService->getGuestDashboard($session);

        return response()->json($dashboard);
    }

    /**
     * GET /api/v1/guest/active-sessions
     * List all active portal sessions for the hotel (Staff view)
     */
    public function activeSessions(Request $request)
    {
        $sessions = GuestPortalSession::with(['room', 'guest', 'reservation'])
            ->where(function($q) use ($request) {
                $q->where('hotel_id', $request->user()->hotel_id);
            })
            ->where(function($q) {
                $q->where('status', '!=', 'revoked');
            })
            ->where(function($q) {
                $q->where('expires_at', '>', now());
            })
            ->get();

        return response()->json(['data' => $sessions]);
    }
}
