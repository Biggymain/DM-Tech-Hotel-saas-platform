<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return $next($request);
        }

        // Super admins circumvent billing checks
        if ($user->is_super_admin) {
            return $next($request);
        }

        $hotel = $user->hotel;

        if (!$hotel) {
            return $next($request);
        }

        // Load subscription if not loaded
        $subscription = $hotel->subscription;

        if (!$subscription || !$subscription->isActive()) {
            return response()->json([
                'error' => 'Payment Required',
                'message' => 'Your subscription is suspended or expired. Please update your billing information.',
                'status' => $subscription ? $subscription->status : 'none'
            ], 402);
        }

        return $next($request);
    }
}
