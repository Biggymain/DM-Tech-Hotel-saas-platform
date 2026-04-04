<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $input = $request->all();
        $whitelist = ['password', 'password_confirmation', 'token', 'session_token', 'transaction_reference', 'api_token'];

        array_walk_recursive($input, function (&$value, $key) use ($whitelist) {
            if (is_string($value) && !in_array($key, $whitelist)) {
                $value = strip_tags($value);
            }
        });

        $request->merge($input);

        return $next($request);
    }
}
