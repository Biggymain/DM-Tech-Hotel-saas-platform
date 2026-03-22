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
            'tenant.branch' => \App\Http\Middleware\TenantBranchContext::class,
            'module.active' => \App\Http\Middleware\ModuleAccessMiddleware::class,
        ]);
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJsonResponse::class,        // 2nd — always JSON
            \App\Http\Middleware\TenantIsolationMiddleware::class, // 3rd — tenant scoping
            \App\Http\Middleware\TenantBranchContext::class,       // newly added context
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
