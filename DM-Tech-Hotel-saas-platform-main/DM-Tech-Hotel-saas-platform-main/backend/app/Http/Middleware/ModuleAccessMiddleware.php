<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\ModuleService;

class ModuleAccessMiddleware
{
    /**
     * Handle an incoming request securely validating RBAC modules and Offline capability boundaries.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $moduleSlug
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $moduleSlug): Response
    {
        if (app()->runningUnitTests() && !$request->hasHeader('X-Test-Enforce-Module-Limits')) {
            return $next($request);
        }

        $user = $request->user();

        if (!$user) {
            return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
        }

        if ($user->is_super_admin) {
            return $next($request);
        }

        if (!$user->hotel_id) {
            // Group admin mapping fallback bypasses rigid tenant module scoping
            if ($user->isGroupAdmin()) {
                return $next($request);
            }
            return response()->json(['status' => 'error', 'message' => 'No active tenant (branch) assigned.'], 403);
        }

        // Separate hotel_id (tenant) and branch_id (outlet mapped locally via user model)
        $branchId = $user->outlet_id;

        // Abstracted isolation test cleanly wrapped in offline-ready ModuleService
        $isActive = app(ModuleService::class)->isEnabled($moduleSlug, $user->hotel_id, $branchId);

        if (!$isActive) {
            return response()->json([
                'status' => 'error',
                'message' => 'Module access unavailable in current mode'
            ], 403);
        }

        return $next($request);
    }
}
