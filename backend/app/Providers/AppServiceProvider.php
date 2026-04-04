<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Login::class,
            \App\Listeners\SendLoginAlert::class
        );

        // Early Tenant Binding for Route-Model Binding resilience
        if (!$this->app->runningInConsole() || $this->app->environment('testing')) {
            $id = $_SERVER['HTTP_X_TENANT_ID'] ?? $_SERVER['HTTP_X_HOTEL_CONTEXT'] ?? null;
            if ($id) {
                $this->app->instance('tenant_id', (int)$id);
            }
        }

        $router = $this->app['router'];
        $router->aliasMiddleware('subscription.active', \App\Http\Middleware\EnsureActiveSubscription::class);
    }
}
