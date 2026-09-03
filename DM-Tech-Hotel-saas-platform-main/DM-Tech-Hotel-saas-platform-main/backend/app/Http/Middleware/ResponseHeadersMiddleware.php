<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds a comprehensive set of security related HTTP response headers.
 *
 * This middleware is intended to be placed early in the middleware stack so
 * that all responses – including error pages – inherit the hardening.
 */
class ResponseHeadersMiddleware
{
    /**
     * Handle an incoming request and inject security headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Strict Transport Security – 1 year, include subdomains, preload ready.
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');

        // X-Content-Type-Options – prevent MIME sniffing.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // X-Frame-Options – deny framing to mitigate clickjacking.
        $response->headers->set('X-Frame-Options', 'DENY');

        // Referrer-Policy – give browsers strict control over referrer information.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions-Policy – disable potentially risky features.
        $response->headers->set('Permissions-Policy', 'geolocation=(), camera=(), microphone=(), payment=(), usb=()');

        // Content-Security-Policy – a baseline CSP that can be extended per route.
        // Note: we keep a minimal default to avoid breaking existing pages.
        $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self' ws://localhost:* http://localhost:*; frame-ancestors 'none';";
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
?>
