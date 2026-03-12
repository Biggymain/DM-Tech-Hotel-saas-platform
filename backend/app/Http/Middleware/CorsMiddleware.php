<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CorsMiddleware
 *
 * Handles CORS for the API since there is no cors.php config file.
 * Allows the Next.js frontend (localhost:3000) and any configured
 * FRONTEND_URL to access the API with credentials (Sanctum cookies + Bearer token).
 */
class CorsMiddleware
{
    private const ALLOWED_ORIGINS = [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:3001',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin', '');

        // Allow configured frontend URL from .env
        $envFrontend = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000'));
        $allowed     = array_unique([...$this->getAllowedOrigins(), $envFrontend]);

        $allowedOrigin = in_array($origin, $allowed) ? $origin : $allowed[0];

        // Handle preflight
        if ($request->isMethod('OPTIONS')) {
            return response('', 204)
                ->header('Access-Control-Allow-Origin', $allowedOrigin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, X-XSRF-TOKEN')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');
        }

        $response = $next($request);

        return $response
            ->header('Access-Control-Allow-Origin', $allowedOrigin)
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, X-XSRF-TOKEN')
            ->header('Access-Control-Allow-Credentials', 'true');
    }

    private function getAllowedOrigins(): array
    {
        return self::ALLOWED_ORIGINS;
    }
}
