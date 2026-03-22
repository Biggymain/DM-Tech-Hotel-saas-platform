<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class TenantBranchContext
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            
            if ($user->tenant_id) {
                app()->singleton('current_tenant_id', fn () => $user->tenant_id);
            }
            if ($user->branch_id) {
                app()->singleton('current_branch_id', fn () => $user->branch_id);
            }
        }

        return $next($request);
    }
}
