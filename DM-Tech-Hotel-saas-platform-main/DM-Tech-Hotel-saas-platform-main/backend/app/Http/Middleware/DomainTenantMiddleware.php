<?php

namespace App\Http\Middleware;

use App\Models\Hotel;
use Closure;
use Illuminate\Http\Request;

/**
 * DomainTenantMiddleware
 *
 * Identifies the hotel_id from the HTTP Host header.
 * Supports two strategies:
 *  1. Custom domain  — e.g. "book.royalspring.com" mapped to hotels.custom_domain
 *  2. Subdomain slug — e.g. "royal-spring.hotelsaas.com" mapped to hotels.subdomain_slug
 *
 * Once resolved, the hotel_id is bound into the application container so that
 * TenantScope picks it up for all models. No auth required — this is a public route.
 */
class DomainTenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost(); // e.g. "book.royalspring.com" or "royal-spring.hotelsaas.com"

        // Strategy 1: exact custom domain match
        $hotel = Hotel::withoutGlobalScopes()->where('custom_domain', $host)->first();

        // Strategy 2: subdomain slug (strip the base domain)
        if (!$hotel) {
            $baseDomain = config('app.base_domain', 'hotelsaas.com');
            if (str_ends_with($host, '.' . $baseDomain)) {
                $slug = str_replace('.' . $baseDomain, '', $host);
                $hotel = Hotel::withoutGlobalScopes()->where('subdomain_slug', $slug)->first();
            }
        }

        // Strategy 3: slug passed in the URL path (for /[slug]/reserve style routes)
        if (!$hotel && $slug = $request->route('hotel_slug')) {
            $hotel = Hotel::withoutGlobalScopes()->where('subdomain_slug', $slug)->first();
        }

        if (!$hotel) {
            return response()->json([
                'error'   => 'Hotel not found for domain: ' . $host,
                'message' => 'This domain is not registered with any hotel on our platform.',
            ], 404);
        }

        // Bind the resolved tenant into the container so TenantScope picks it up
        app()->instance('tenant_id', $hotel->id);
        app()->instance('tenant_hotel', $hotel);

        // Pass the hotel to the request for use in controllers
        $request->attributes->set('tenant_hotel', $hotel);
        $request->attributes->set('tenant_hotel_id', $hotel->id);

        return $next($request);
    }
}
