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
            $user = Auth::user();
            $hotelId = $user->hotel_id;

            // Allow Group Admins and Super Admins to switch context via header or slug
            $contextId = $request->header('X-Hotel-Context') 
                      ?? $request->query('hotel_id')
                      ?? $request->input('hotel_id');

            if (!$contextId && ($request->query('hotel_slug') || $request->input('hotel_slug'))) {
                $slug = $request->query('hotel_slug') ?? $request->input('hotel_slug');
                $contextId = \App\Models\Hotel::where('slug', $slug)->value('id');
            }

            if (!$hotelId && $contextId) {
                if ($user->is_super_admin) {
                    // Super Admin can switch to ANY hotel
                    $hotelId = $contextId;
                } else if ($user->hotel_group_id) {
                    // Group Admin: Verify this hotel belongs to the user's group
                    $exists = \App\Models\Hotel::where('id', $contextId)
                        ->where('hotel_group_id', $user->hotel_group_id)
                        ->exists();
                    if ($exists) {
                        $hotelId = $contextId;
                    }
                }
            }
        } 
        
        // 2. Check Guest Portal Session (via Token in Header, Query or Body)
        if (!$hotelId) {
            $token = $request->header('X-Guest-Session-Token') 
                  ?? $request->header('X-Guest-Session')
                  ?? $request->query('session_token') 
                  ?? $request->input('session_token');

            if ($token) {
                $session = GuestPortalSession::where('session_token', $token)
                    ->where('expires_at', '>', now())
                    ->first();
                
                if ($session) {
                    $hotelId = $session->hotel_id;
                    // Store session for later use in controllers
                    $request->attributes->add(['guest_session' => $session]);
                }
            }
        }

        $isGroupAdmin = Auth::check() && Auth::user()->hotel_group_id && !Auth::user()->hotel_id;
        $isSuperAdmin = Auth::check() && Auth::user()->is_super_admin;

        if (!$hotelId && !$isGroupAdmin && !$isSuperAdmin && !$this->isPublicRoute($request)) {
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
               $request->is('api/v1/guest/session/authenticate') ||
               $request->is('api/v1/payments/webhook/*');
    }
}
