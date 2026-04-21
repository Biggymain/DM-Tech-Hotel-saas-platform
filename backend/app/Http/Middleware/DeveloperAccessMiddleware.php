<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DeveloperAccessMiddleware
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
        $devKey = $request->header('X-Supabase-Dev-Key');
        $passphrase = $request->input('passphrase');

        if (!$devKey || $devKey !== config('fortress.supabase_dev_key')) {
            return response()->json(['error' => 'Unauthorized Sentry Key'], 401);
        }

        $hashedPassphrase = config('fortress.dev_passphrase_hash');

        if (!$passphrase || !$hashedPassphrase || !Hash::check($passphrase, $hashedPassphrase)) {
            return response()->json(['error' => 'Unauthorized Developer Passphrase'], 401);
        }

        return $next($request);
    }
}
