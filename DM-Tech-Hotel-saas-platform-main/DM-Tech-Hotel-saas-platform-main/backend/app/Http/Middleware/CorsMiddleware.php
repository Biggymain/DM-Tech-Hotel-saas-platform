<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CorsMiddleware
 *
 * Handles CORS for the API across the 6-port frontend architecture.
 * Allows all Next.js frontends (localhost:3000-3005) and any configured
 * FRONTEND_URL to access the API with credentials (Sanctum cookies + Bearer token).
 *
 * CRITICAL: OPTIONS preflight requests are returned immediately with a 204
 * to prevent auth middleware from intercepting them and returning false 401/403s.
 */
class CorsMiddleware
{
    /**
     * All 6 frontend ports + backend loopback.
     */
    private const ALLOWED_ORIGINS = [
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:3002',
        'http://localhost:3003',
        'http://localhost:3004',
        'http://localhost:3005',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:3001',
        'http://127.0.0.1:3002',
        'http://127.0.0.1:3003',
        'http://127.0.0.1:3004',
        'http://127.0.0.1:3005',
    ];

    /**
     * All custom headers that frontends inject via Axios interceptors.
     * These MUST be declared here so OPTIONS preflight responses include them,
     * otherwise the browser will block the actual request with a false CORS error.
     */
    private const ALLOWED_HEADERS = 'Content-Type, Authorization, X-Requested-With, Accept, X-XSRF-TOKEN, X-Frontend-Port, X-App-Port, X-Tenant-Slug, X-Hotel-Context, X-Hardware-Id, X-Room-ID, X-Outlet-ID, X-Table-Number, X-Tenant-ID, X-Branch-ID, X-Group-ID';

    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin', '');

        // Allow configured frontend URL from .env
        $envFrontend = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));

        // Also parse CORS_ALLOWED_ORIGINS from .env for dynamic expansion
        $envOrigins = array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', '')));

        $allowed = array_unique(array_merge(self::ALLOWED_ORIGINS, [$envFrontend], $envOrigins));

        // Check if the request origin matches any allowed origin or any subdomain pattern
        $allowedOrigin = in_array($origin, $allowed) ? $origin : null;

        // Support *.localhost subdomain patterns (e.g. ikeja.royalspring.localhost:3002)
        if (!$allowedOrigin && preg_match('#^http://[a-zA-Z0-9._-]+\.localhost(:[0-9]+)?$#', $origin)) {
            $allowedOrigin = $origin;
        }

        // Fallback to first allowed origin if no match
        $allowedOrigin = $allowedOrigin ?: $allowed[0];

        // ── CRITICAL: Handle preflight OPTIONS immediately ──────────────────
        // Return 204 before any auth/middleware chain can reject with 401/403.
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)
                ->header('Access-Control-Allow-Origin', $allowedOrigin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', self::ALLOWED_HEADERS)
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');
        }

        $response = $next($request);

        return $response
            ->header('Access-Control-Allow-Origin', $allowedOrigin)
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', self::ALLOWED_HEADERS)
            ->header('Access-Control-Allow-Credentials', 'true');
    }
}
