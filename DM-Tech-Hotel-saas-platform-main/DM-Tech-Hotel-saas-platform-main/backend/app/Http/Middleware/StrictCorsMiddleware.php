<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strict CORS handling for the multi‑port development environment.
 *
 * - Allows only the six local frontend ports (3000‑3005) and their sub‑domains.
 * - Handles pre‑flight OPTIONS requests **before** any authentication middleware.
 * - Exposes the custom security headers used by the platform.
 */
class StrictCorsMiddleware
{
    /**
     * List of allowed origins – ports 3000‑3005 on localhost and 127.0.0.1.
     */
    protected array $allowedOrigins = [
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
     * List of custom headers used by the platform.
     */
    protected array $exposedHeaders = [
        'X-Frontend-Port',
        'X-App-Port',
        'X-Tenant-Slug',
        'Authorization',
        'Content-Type',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('origin');
        $method = $request->getMethod();

        // If the request is not from an allowed origin, reject early.
        if ($origin && !in_array($origin, $this->allowedOrigins, true)) {
            return response()->json(['error' => 'CORS origin not allowed'], 403);
        }

        // Pre‑flight OPTIONS – respond immediately with the required headers.
        if (strtoupper($method) === 'OPTIONS') {
            $response = new Response('', 204);
            $response->headers->set('Access-Control-Allow-Origin', $origin ?? '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET,POST,PUT,PATCH,DELETE,OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', implode(', ', $this->exposedHeaders));
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $this->exposedHeaders));
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Max-Age', '86400');
            return $response;
        }

        $response = $next($request);

        // Add CORS headers to normal responses.
        if ($origin) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Expose-Headers', implode(', ', $this->exposedHeaders));

        return $response;
    }
}
?>
