<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class TenantIsolationMiddleware
{
    /**
     * Routes that are ALWAYS exempt from tenant isolation.
     * These are public/central-SaaS endpoints that don't belong to any tenant.
     */
    private const BYPASS_ROUTES = [
        'api/v1/auth/login',
        'api/v1/auth/register',
        'api/v1/auth/register-group',
        'api/v1/auth/register-hotel',
        'api/v1/auth/forgot-password',
        'api/v1/auth/reset-password',
        'api/v1/booking',         // Public booking engine
        'api/v1/integration',     // Lock webhooks (auth via HMAC, not session)
        'api/v1/payments/webhook',// Payment webhooks
        'api/v1/channels/webhook',// OTA webhooks
        'up',                     // Health check
        '',                       // Root / Landing page
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // ── 1. Skip for public/central routes (no auth needed at all) ──────────
        $path = ltrim($request->path(), '/');
        foreach (self::BYPASS_ROUTES as $bypass) {
            if (str_starts_with($path, ltrim($bypass, '/'))) {
                return $next($request);
            }
        }

        // ── 2. Skip for unauthenticated requests — auth middleware will handle it
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();

        // ── 3. SUPER_ADMIN passes without any tenant context ───────────────────
        if ($user->is_super_admin) {
            return $next($request);
        }

        // ── 4. GROUP_ADMIN: has a hotel_group_id but hotel_id may be null ─────
        //    They manage multiple branches, not scoped to one hotel.
        if (!empty($user->hotel_group_id)) {
            return $next($request);
        }

        // ── 5. Regular staff: must have a hotel_id assigned ───────────────────
        if (empty($user->hotel_id)) {
            return response()->json([
                'error'   => 'Tenant unresolved',
                'message' => 'Your account is not assigned to a hotel branch. '
                           . 'Please contact your Group Administrator.',
            ], 403);
        }

        // Bind the tenant ID into the service container for TenantScope usage
        app()->instance('tenant_id', $user->hotel_id);
        app()->instance('active_hotel_id', $user->hotel_id);

        return $next($request);
    }
}
