<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class TenantIsolationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Enforce tenant presence for non SuperAdmin users
            if (!$user->is_super_admin && empty($user->hotel_id)) {
                return response()->json([
                    'error' => 'Unauthorized by Tenant Manager',
                    'message' => 'User is not assigned to any hotel tenant.'
                ], 403);
            }

            // Bind the active tenant ID into the service container for optional usage
            if (!empty($user->hotel_id)) {
                app()->instance('tenant_id', $user->hotel_id);
            }
        }

        return $next($request);
    }
}
