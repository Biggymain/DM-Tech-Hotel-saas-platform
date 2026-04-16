<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['auth:sanctum', 'tenant']],
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role.verify'   => \App\Http\Middleware\RoleVerificationMiddleware::class,
            'tenant'        => \App\Http\Middleware\TenantIsolationMiddleware::class,
            'tenant.branch' => \App\Http\Middleware\Tenant\TenantBranchContext::class,
            'module.active' => \App\Http\Middleware\ModuleAccessMiddleware::class,
        ]);
        $middleware->api(prepend: [
//            \App\Http\Middleware\SanitizeInput::class, // Strip tags first
            \App\Http\Middleware\Security\SecureHeadersMiddleware::class, // Execute security headers safely first 
            \App\Http\Middleware\ForceJsonResponse::class,        // 2nd — always JSON
            \App\Http\Middleware\SentryMiddleware::class,         // 3rd — Hardware & Approval Gatekeeper
            \App\Http\Middleware\TenantIsolationMiddleware::class, // 4th — tenant scoping
            \App\Http\Middleware\Tenant\TenantBranchContext::class,       // newly added context
        ]);
        
        // Recommended additionally lock down web interfaces
        $middleware->web(prepend: [
            \App\Http\Middleware\SanitizeInput::class,
            \App\Http\Middleware\Security\SecureHeadersMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
