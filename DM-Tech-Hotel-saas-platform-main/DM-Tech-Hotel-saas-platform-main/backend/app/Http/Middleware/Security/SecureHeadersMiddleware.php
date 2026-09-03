<?php

namespace App\Http\Middleware\Security;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecureHeadersMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        // Antigravity Fix: Resolve the exact frontend origin port for strict CSP isolation,
        // rather than defaulting to the backend's port 8000.
        $origin = $request->header('origin') ?? '';
        $port = parse_url($origin, PHP_URL_PORT) ?? $request->getPort();
        
        // Formulate strict Port-based Content-Security-Policy
        $csp = "default-src 'self' localhost:{$port} 127.0.0.1:{$port}; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
               "style-src 'self' 'unsafe-inline'; " .
               "img-src 'self' data: https:; " .
               "font-src 'self' data: https:; " .
               "connect-src 'self' ws://localhost:{$port} http://localhost:{$port}; " .
               "frame-ancestors 'none';";

        // Application of Mandatory Security Headers
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
