<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\GuestPortalSession;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hotelId = null;

        // 1. Check Authenticated Admin User
        if (Auth::check()) {
            $hotelId = Auth::user()->hotel_id;
        } 
        // 2. Check Guest Portal Session (via Token in Header)
        elseif ($request->header('X-Guest-Session-Token')) {
            $session = GuestPortalSession::where('session_token', $request->header('X-Guest-Session-Token'))
                ->where('expires_at', '>', now())
                ->first();
            
            if ($session) {
                $hotelId = $session->hotel_id;
                // Store session for later use in controllers
                $request->attributes->add(['guest_session' => $session]);
            }
        }

        if (!$hotelId && !$this->isPublicRoute($request)) {
            return response()->json([
                'error' => 'Tenant Resolution Failed',
                'message' => 'Valid hotel context or guest session required.'
            ], 403);
        }

        if ($hotelId) {
            app()->instance('tenant_id', $hotelId);
            $request->merge(['hotel_id' => $hotelId]);
        }

        return $next($request);
    }

    /**
     * Check if the request is for a public route that doesn't require tenant context.
     */
    protected function isPublicRoute(Request $request): bool
    {
        return $request->is('api/v1/auth/register-hotel') || 
               $request->is('api/v1/guest/session/start') ||
               $request->is('api/v1/payments/webhook/*');
    }
}
