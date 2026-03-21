<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\ActivityLogService;

class LogUserActivityMiddleware
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $executionTime = microtime(true) - $startTime;

        if ($request->user() && in_array($request->method(), ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $action = strtolower($request->method()) . '_api_request';
            
            // Map routes to custom actions naturally
            if ($request->is('api/v1/menu/items*')) $action = 'menu_item_mutation';
            if ($request->is('api/v1/orders*')) $action = 'order_mutation';
            if ($request->is('api/v1/billing/payments*')) $action = 'payment_mutation';
            if ($request->is('api/v1/inventory/adjustments*')) $action = 'inventory_adjustment';

            $this->activityLogService->logAction(
                hotelId: $request->user()->hotel_id,
                action: $action,
                description: "API request to {$request->path()}",
                userId: $request->user()->id,
                outletId: $request->input('outlet_id'),
                severity: $response->getStatusCode() >= 400 ? 'warning' : 'info',
                metadata: [
                    'endpoint' => $request->path(),
                    'method' => $request->method(),
                    'status' => $response->getStatusCode(),
                    'execution_time_ms' => round($executionTime * 1000, 2),
                ],
                requestData: [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]
            );
        }

        return $response;
    }
}
