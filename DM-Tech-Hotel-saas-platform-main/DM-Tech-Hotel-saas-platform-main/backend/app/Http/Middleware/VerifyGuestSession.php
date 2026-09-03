<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\GuestPortalSession;

class VerifyGuestSession
{
    public function handle(Request $request, Closure $next)
    {
        $sessionToken = $request->cookie('guest_session') ?? $request->header('X-Guest-Session');
        
        if (!$sessionToken) {
            return response()->json(['message' => 'No session token provided.'], 401);
        }

        $session = GuestPortalSession::where('session_token', $sessionToken)->first();

        if (!$session || $session->status === 'revoked' || $session->expires_at < now()) {
            return response()->json(['message' => 'Session expired or revoked.'], 401);
        }

        // The "Double-Lock" Check
        // Allow GET requests (like viewing the menu), but reject ordering operations until activated.
        if ($session->status === 'pending_activation' && !$request->isMethod('get')) {
            return response()->json([
                'message' => 'Waiting for waitress verification. Session locked.',
                'status' => 'pending_activation'
            ], 403);
        }

        // Heartbeat: update last_activity_at to prevent reaper from killing active guest
        $session->update(['last_activity_at' => now()]);

        // Attach session to request for controllers
        $request->attributes->add(['guest_session' => $session]);

        return $next($request);
    }
}
