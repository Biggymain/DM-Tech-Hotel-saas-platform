<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Services\HardwareValidationService;
use Mockery;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure fresh instances for every test to prevent shared state contamination
        $this->app->forgetInstance(\App\Http\Middleware\SentryMiddleware::class);
        $this->app->forgetInstance(\App\Services\HardwareValidationService::class);
        $this->app->forgetInstance(\App\Services\HardwareFingerprintService::class);
        $this->app->forgetInstance(\App\Services\FortressLockService::class);

        // 1. Service-Level Mocking for Hardware Validation
        $this->mock(HardwareValidationService::class, function ($mock) {
            $mock->shouldReceive('validate')->andReturnUsing(function($hash) {
                if ($hash === 'locked-hardware-hash') {
                    return [
                        'id' => 'dev-locked',
                        'is_manually_locked' => true,
                        'is_approved' => true,
                        'expires_at' => \Carbon\Carbon::now()->addYear()->toDateTimeString(),
                        'device_active' => true,
                    ];
                }
                if ($hash === 'unknown-hardware-hash') {
                    return null;
                }
                return [
                    'id' => 'dev-valid',
                    'is_manually_locked' => false,
                    'is_approved' => true,
                    'expires_at' => \Carbon\Carbon::now()->addYear()->toDateTimeString(),
                    'device_active' => true,
                    'manager_email' => 'manager@hotel.com',
                    'owner_email' => 'owner@hotel.com'
                ];
            });
        });

        // Global Mock for Hardware Fingerprint Capture
        $this->mock(\App\Services\HardwareFingerprintService::class, function ($mock) {
            $mock->shouldReceive('generateHash')->andReturn('valid-hardware-hash')->byDefault();
        });

        // 2. Functional Mock for Supabase PGP functions
        Config::set('database.connections.supabase', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        
        $conn = DB::connection('supabase');
        $pdo = $conn->getPdo();
        
        // Create necessary tables for licensing sync mocks
        $pdo->exec("CREATE TABLE branches (id TEXT PRIMARY KEY, group_id TEXT, expires_at DATETIME, manager_email TEXT, owner_email TEXT, created_at DATETIME)");
        
        $pdo->sqliteCreateFunction('encrypt_sensitive_data', fn($d, $p) => "encrypted_{$d}");
        $pdo->sqliteCreateFunction('decrypt_sensitive_data', fn($d, $p) => strtr($d, ['encrypted_' => '']));

    }

    public function actingAs($user, $guard = null)
    {
        if ($user instanceof \App\Models\User) {
            // Force persistence of security states to satisfy SentryMiddleware Gates (503/403)
            $user->forceFill([
                'hardware_hash' => 'valid-hardware-hash',
                'is_approved' => true,
                'is_on_duty' => true,
            ])->save();
        }

        return $this->withAutoPort($user)->parentActingAs($user, $guard);
    }

    /**
     * Override call to inject SIEM security headers at the lowest level.
     * This ensures GET, POST, PUT, DELETE are all 'Fortress-Compliant'.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if (app()->environment('testing')) {
            \Log::info("TestCase: call() hit", ['uri' => $uri, 'method' => $method]);
        }

        // 1. Hardware ID Injection (Default to valid test hash)
        $server['HTTP_X_HARDWARE_ID'] = $server['HTTP_X_HARDWARE_ID'] ?? $this->defaultHeaders['X-Hardware-Id'] ?? 'valid-hardware-hash';
        
        // 2. Late-Bound Port Detection
        // This supports tests that dynamically attach roles AFTER calling actingAs()
        // We prioritize explicit $server overrides, then dynamic user-role detection, then defaultHeaders.
        if (!isset($server['HTTP_X_FRONTEND_PORT'])) {
            $user = \Illuminate\Support\Facades\Auth::user();
            if ($user && $user instanceof \App\Models\User) {
                $portMapping = config('fortress.port_mapping', []);
                $assignedPort = null;
                
                // Use a fresh query to bypass any relationship caching from the test setup
                $roleSlugs = \Illuminate\Support\Facades\DB::table('user_roles')
                    ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                    ->where('user_roles.user_id', $user->id)
                    ->pluck('roles.slug')
                    ->toArray();

                if (app()->environment('testing')) {
                    \Log::info("TestCase: Raw roles for user " . $user->id, [
                        'slugs' => $roleSlugs
                    ]);
                }

                foreach ($roleSlugs as $slug) {
                    if (isset($portMapping[$slug])) {
                        $assignedPort = $portMapping[$slug];
                        break;
                    }
                }
                
                if ($assignedPort) {
                    $server['HTTP_X_FRONTEND_PORT'] = (string) $assignedPort;
                }
            }
        }

        // Final Fallback to defaultHeaders or 3000
        $finalPort = $server['HTTP_X_FRONTEND_PORT'] ?? $this->defaultHeaders['X-Frontend-Port'] ?? '3000';
        
        $server['HTTP_X_FRONTEND_PORT'] = (string) $finalPort;
        $this->serverVariables['HTTP_X_FRONTEND_PORT'] = (string) $finalPort;
        $this->serverVariables['SERVER_PORT'] = (string) $finalPort;
        $this->serverVariables['HTTP_HOST'] = "localhost:{$finalPort}";

        $server['HTTP_X_FRONTEND_PORT'] = (string) $finalPort;
        $server['SERVER_PORT'] = (string) $finalPort;
        $server['HTTP_HOST'] = "localhost:{$finalPort}";

        $this->withHeaders([
            'X-Frontend-Port' => (string) $finalPort,
            'X-Hardware-Id'   => $server['HTTP_X_HARDWARE_ID']
        ]);

        if (app()->environment('testing')) {
            \Log::info("TestCase: call() final server vars", [
                'X-Frontend-Port' => $server['HTTP_X_FRONTEND_PORT'] ?? 'MISSING',
                'SERVER_PORT' => $server['SERVER_PORT'] ?? 'MISSING'
            ]);
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /**
     * Helper to call parent actingAs without recursion
     */
    protected function parentActingAs($user, $guard = null)
    {
        $this->defaultHeaders['X-Hardware-Id'] = 'valid-hardware-hash';
        return parent::actingAs($user, $guard);
    }

    /**
     * Set the X-Frontend-Port and Host headers for port-isolated tests.
     */
    public function withPort(int $port): self
    {
        $this->defaultHeaders['X-Frontend-Port'] = (string) $port;
        $this->defaultHeaders['Host'] = "localhost:{$port}";
        
        return $this->withHeaders([
            'X-Frontend-Port' => (string) $port,
            'Host' => "localhost:{$port}",
        ]);
    }

    /**
     * Automatically set the correct port header based on the user's role.
     */
    public function withAutoPort($user): self
    {
        $portMapping = config('fortress.port_mapping', []);
        $assignedPort = 3000; // Default

        if ($user && $user instanceof \App\Models\User) {
            // Ensure roles are loaded from the database to reflect recent attachments in tests
            // withoutGlobalScopes is critical because system roles have hotel_id = null
            $roles = $user->roles()->withoutGlobalScopes()->get();
            foreach ($roles as $role) {
                if (isset($portMapping[$role->slug])) {
                    $assignedPort = $portMapping[$role->slug];
                    break;
                }
            }
        }

        return $this->withPort($assignedPort);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
