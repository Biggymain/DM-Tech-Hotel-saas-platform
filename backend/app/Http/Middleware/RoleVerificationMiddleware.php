<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\PermissionService;

class RoleVerificationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        // Check if user is authenticated and if they have permission
        if (!$user || !app(PermissionService::class)->hasPermission($user, $permission)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Permission denied'
            ], 403);
        }

        return $next($request);
    }
}
