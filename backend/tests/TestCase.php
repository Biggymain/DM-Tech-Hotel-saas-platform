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
    public static ?string $virtualTwinHash = null;

    /**
     * Virtual Twin: Standardized System-Wide Test Hash (Argon2id statically cached)
     */
    public static function generateMockHardwareHash(): string
    {
        if (self::$virtualTwinHash === null) {
            self::$virtualTwinHash = \Illuminate\Support\Facades\Hash::make('virtual_twin_v1');
        }
        return self::$virtualTwinHash;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Clear stateful instances
        $this->app->forgetInstance(\App\Http\Middleware\SentryMiddleware::class);
        $this->app->forgetInstance(\App\Services\HardwareValidationService::class);
        $this->app->forgetInstance(\App\Services\HardwareFingerprintService::class);
        $this->app->forgetInstance(\App\Services\FortressLockService::class);

        // 2. Global Header Injection
        $this->withHeaders([
            'X-Hardware-Id' => self::generateMockHardwareHash()
        ]);

        // 3. Functional Mock for Supabase Connection
        Config::set('database.connections.supabase', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        
        $schema = \Illuminate\Support\Facades\Schema::connection('supabase');
        
        if (!$schema->hasTable('branches')) {
            // Strict Schema: NO NULLABLES except for auditing/optional manager emails where sensible
            $schema->create('branches', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->string('id')->primary();
                $table->string('branch_token');
                $table->string('group_id');
                $table->dateTime('expires_at');
                $table->string('manager_email');
                $table->string('owner_email');
                $table->integer('is_active')->default(1);
                $table->dateTime('created_at');
            });

            $schema->create('devices', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('branch_id');
                $table->string('hardware_hash');
                $table->string('device_uuid'); // Mandatory Forensic Field
                $table->integer('is_active')->default(1);
                $table->integer('is_manually_locked')->default(0);
                $table->dateTime('expires_at');
                $table->dateTime('last_sync');
                $table->dateTime('updated_at');
            });

            // 4. Seeding the Virtual Twin
            $hash = self::generateMockHardwareHash();
            
            DB::connection('supabase')->table('branches')->insert([
                'id' => 'branch-1',
                'branch_token' => 'virtual-token-v1',
                'group_id' => 'group-1',
                'expires_at' => now()->addYear(),
                'manager_email' => 'manager@dmtech.local',
                'owner_email' => 'owner@dmtech.local',
                'is_active' => 1,
                'created_at' => now()
            ]);

            DB::connection('supabase')->table('devices')->insert([
                'branch_id' => 'branch-1',
                'hardware_hash' => $hash,
                'device_uuid' => 'virtual_twin_v1',
                'is_active' => 1,
                'is_manually_locked' => 0,
                'expires_at' => now()->addYear(),
                'last_sync' => now(),
                'updated_at' => now()
            ]);

            // Add forensic scenarios for existing test expectations
            DB::connection('supabase')->table('devices')->insert([
                'branch_id' => 'branch-1',
                'hardware_hash' => 'locked-hardware-hash',
                'device_uuid' => 'locked-v1',
                'is_active' => 1,
                'is_manually_locked' => 1,
                'expires_at' => now()->addYear(),
                'last_sync' => now(),
                'updated_at' => now()
            ]);
        }
        
        $pdo = \DB::connection('supabase')->getPdo();
        
        if (!$pdo->inTransaction()) {
            $pdo->sqliteCreateFunction('encrypt_sensitive_data', fn($d, $p) => "encrypted_{$d}");
            $pdo->sqliteCreateFunction('decrypt_sensitive_data', fn($d, $p) => strtr($d, ['encrypted_' => '']));
        }
    }

    public function actingAs($user, $guard = null)
    {
        if ($user instanceof \App\Models\User) {
            // Force persistence of security states to satisfy SentryMiddleware Gates (503/403)
            $user->forceFill([
                'hardware_hash' => self::generateMockHardwareHash(),
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
        $server['HTTP_X_HARDWARE_ID'] = $server['HTTP_X_HARDWARE_ID'] ?? $this->defaultHeaders['X-Hardware-Id'] ?? self::generateMockHardwareHash();
        
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
        $this->defaultHeaders['X-Hardware-Id'] = self::generateMockHardwareHash();
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
