<?php

namespace App\Http\Middleware\Tenant;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantBranchContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $branchId = $request->header('X-Branch-ID');
        
        if ($branchId) {
            app()->singleton('current_branch_id', fn () => $branchId);
        }

        return $next($request);
    }
}
