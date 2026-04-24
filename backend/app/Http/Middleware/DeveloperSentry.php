<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DeveloperSentry
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $device = $request->attributes->get('hardware_device_record');
        
        if (!$device || ($device['access_level'] ?? null) !== 'master') {
            \Illuminate\Support\Facades\Log::error('DeveloperSentry Access Denied', [
                'device' => $device,
                'hardware_id_header' => $request->header('X-Hardware-Id')
            ]);
            abort(403, 'Unauthorized: Master Access Required');
        }

        return $next($request);
    }
}
