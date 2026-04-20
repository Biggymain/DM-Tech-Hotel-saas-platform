<?php

namespace App\Services;

use Illuminate\Support\Facades\Request;
use App\Models\GuestPortalSession;

class TransactionContextService
{
    /**
     * Automatically capture verification metadata for guest actions.
     */
    public function captureContext(): array
    {
        $request = Request::instance();
        $sessionToken = $request->header('X-Session-Token') ?? $request->input('session_token');
        
        $guestId = null;
        $reservationId = null;
        $roomId = null;
        $deviceFingerprint = $request->input('device_fingerprint') ?? $request->header('X-Device-Fingerprint');
        $actionSource = 'api';

        if ($sessionToken) {
            $session = GuestPortalSession::where('session_token', $sessionToken)
                ->where('status', '!=', 'revoked')
                ->first();
            
            if ($session) {
                $guestId = $session->guest_id;
                $reservationId = $session->reservation_id;
                $roomId = $session->room_id;
                $actionSource = 'guest_portal';
                
                // Use portal fingerprint if it's there
                if (!$deviceFingerprint) {
                    $deviceFingerprint = $session->device_fingerprint;
                }
            }
        }

        return [
            'guest_id' => $guestId,
            'reservation_id' => $reservationId,
            'room_id' => $roomId,
            'device_fingerprint' => $deviceFingerprint,
            'ip_address' => $request->ip(),
            'session_token' => $sessionToken,
            'action_source' => $actionSource,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
