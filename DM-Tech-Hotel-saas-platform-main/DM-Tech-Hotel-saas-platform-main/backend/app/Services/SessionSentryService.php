<?php

namespace App\Services;

use App\Models\GuestPortalSession;
use App\Models\StaffDailyPin;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SessionSentryService
{
    /**
     * Activates a pending session via Waitress PIN Handshake.
     */
    public function activate(string $sessionToken, int $waiterId, string $waiterPin): GuestPortalSession
    {
        $session = GuestPortalSession::where('session_token', $sessionToken)->first();

        if (!$session) {
            throw new \Exception('Session not found.', 404);
        }

        if ($session->status !== 'pending_activation') {
            throw new \Exception('Session is not pending activation.', 400);
        }

        $dailyPin = StaffDailyPin::where('user_id', $waiterId)
            ->where('expires_at', '>', now())
            ->first();

        if (!$dailyPin || !Hash::check($waiterPin, $dailyPin->pin_hash)) {
            \App\Services\AuditLogService::log(
                'guest_session',
                $session->id,
                'failed_handshake',
                null, null,
                "Waitress PIN verification failed for guest session activation.",
                'api',
                $session->hotel_id,
                $waiterId
            );
            throw new \Exception('Invalid PIN. Handshake failed.', 403);
        }

        $session->status = 'active';
        $session->waiter_id = $waiterId;
        $session->last_activity_at = now();
        $session->save();

        return $session;
    }

    /**
     * Immediately revokes a guest portal session and flags the client-side cookie for destruction.
     */
    public function revoke($sessionIdOrToken): void
    {
        $session = is_numeric($sessionIdOrToken) 
            ? GuestPortalSession::find($sessionIdOrToken)
            : GuestPortalSession::where('session_token', $sessionIdOrToken)->first();

        if ($session && $session->status !== 'revoked') {
            $session->status = 'revoked';
            $session->save();
            
            // Queue cookie forget if in web request lifecycle
            Cookie::queue(Cookie::forget('guest_session'));
        }
    }

    /**
     * Reap sessions that have been scanning but not verified within 5 minutes.
     */
    public function reapPendingSessions(): int
    {
        return GuestPortalSession::where('status', 'pending_activation')
            ->where('last_activity_at', '<', now()->subMinutes(5))
            ->delete();
    }
}
