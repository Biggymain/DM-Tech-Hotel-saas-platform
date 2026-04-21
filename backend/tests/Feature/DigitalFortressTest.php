<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

use App\Services\HardwareFingerprintService;
use App\Services\HardwareValidationService;
use App\Services\FortressLockService;
use App\Http\Middleware\SentryMiddleware;
use Illuminate\Http\Request;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use PHPUnit\Framework\Attributes\Test;

class DigitalFortressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // Ensure log file is clean for SIEM verification
        if (File::exists(storage_path('logs/fortress_siem.log'))) {
            File::put(storage_path('logs/fortress_siem.log'), '');
        }
    }

    /**
     * Step 1: Database Connectivity Check
     */
    #[Test]
    public function test_supabase_connection_exists()
    {
        $connections = config('database.connections');
        $this->assertArrayHasKey('supabase', $connections);
    }

    /**
     * Step 2: Zero-Knowledge (ZK) Encryption Test
     */
    #[Test]
    public function test_data_at_rest_is_secure()
    {
        $user = \App\Models\User::factory()->create([
            'password' => 'Secret Message'
        ]);

        $rawPassword = DB::table('users')->where('id', $user->id)->value('password');

        $this->assertNotEquals('Secret Message', $rawPassword);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Secret Message', $rawPassword));
    }

    /**
     * Step 3: Global Hardware Sentry - Valid
     */
    #[Test]
    public function test_sentry_middleware_scenario_active()
    {
        $hash = \Tests\TestCase::generateMockHardwareHash();
        $request = new Request();
        $request->headers->set('X-Hardware-Id', $hash);
        $request->headers->set('X-Frontend-Port', '3000'); // SuperAdmin port

        $middleware = new SentryMiddleware(
            $this->app->make(\App\Services\FortressLockService::class),
            $this->app->make(HardwareFingerprintService::class),
            $this->app->make(HardwareValidationService::class)
        );

        $response = $middleware->handle($request, fn() => response('OK', 200));
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Step 3: Global Hardware Sentry - Locked (403 Forbidden)
     */
    #[Test]
    public function test_sentry_middleware_scenario_locked()
    {
        $hash = 'locked-hardware-hash';
        $request = new Request();
        $request->headers->set('X-Hardware-Id', $hash);
        $request->headers->set('X-Frontend-Port', '3000');

        $middleware = new SentryMiddleware(
            $this->app->make(\App\Services\FortressLockService::class),
            $this->app->make(HardwareFingerprintService::class),
            $this->app->make(HardwareValidationService::class)
        );

        try {
            $middleware->handle($request, fn() => response('OK', 200));
            $this->fail('Locked hardware did not trigger abort.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertStringContainsString('Branch manually locked', $e->getMessage());
        }
    }

    /**
     * Step 3: Global Hardware Sentry - Unregistered (403 Forbidden)
     */
    #[Test]
    public function test_sentry_middleware_scenario_unregistered()
    {
        $hash = 'unknown-hardware-hash';
        $request = new Request();
        $request->headers->set('X-Hardware-Id', $hash);
        $request->headers->set('X-Frontend-Port', '3000');

        $middleware = new SentryMiddleware(
            $this->app->make(\App\Services\FortressLockService::class),
            $this->app->make(HardwareFingerprintService::class),
            $this->app->make(HardwareValidationService::class)
        );

        try {
            $middleware->handle($request, fn() => response('OK', 200));
            $this->fail('Unregistered hardware did not trigger abort.');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertStringContainsString('Hardware Not Registered', $e->getMessage());
        }
    }

    /**
     * Port Enforcement: Ghosting (404 Not Found)
     */
    #[Test]
    public function test_sentry_middleware_port_violation_ghosting()
    {
        $user = \App\Models\User::factory()->create();
        $user->roles()->create(['name' => 'branchmanager', 'slug' => 'branchmanager']);
        config(['fortress.port_mapping' => ['branchmanager' => 3001]]);

        $this->mock(HardwareFingerprintService::class, function ($mock) {
            $mock->shouldReceive('generateHash')->andReturn(\Tests\TestCase::generateMockHardwareHash());
        });

        $request = Request::create('/api/dashboard', 'GET');
        $request->setUserResolver(fn() => $user);
        $request->headers->set('X-Frontend-Port', '3000');
        $request->headers->set('X-Hardware-Id', \Tests\TestCase::generateMockHardwareHash());

        $middleware = $this->app->make(SentryMiddleware::class);
        
        try {
            $middleware->handle($request, fn() => response('OK', 200));
            $this->fail('Port violation did not trigger abort.');
        } catch (HttpException $e) {
            $this->assertEquals(404, $e->getStatusCode());
        }
    }

    /**
     * Scenario 8: System Lockdown (503 Service Unavailable)
     */
    #[Test]
    public function test_sentry_middleware_lockdown()
    {
        $lockService = $this->app->make(\App\Services\FortressLockService::class);
        $lockService->triggerLock();

        $middleware = $this->app->make(SentryMiddleware::class);
        $response = $middleware->handle(new Request(), function($req) {
            return response('OK', 200);
        });

        $this->assertEquals(503, $response->getStatusCode());
        $this->assertStringContainsString('SYSTEM LOCKDOWN', $response->getContent());

        $lockService->releaseLock();
    }

    /**
     * Scenario 9: Hardware Marriage Enforcement during Login
     */
    #[Test]
    public function test_login_hardware_marriage_enforcement_and_siem_audit()
    {
        $user = \App\Models\User::factory()->create([
            'email' => 'group@admin.com',
            'is_super_admin' => true,
            'hardware_hash' => 'authorized-device-id'
        ]);

        $this->mock(HardwareFingerprintService::class, function ($mock) {
            $mock->shouldReceive('generateHash')->andReturn(\Tests\TestCase::generateMockHardwareHash());
        });

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'group@admin.com',
            'password' => 'secret-password'
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Hardware Handshake Mismatch', $response->getContent());

        // SIEM Log Verification
        $logPath = storage_path('logs/fortress_siem.log');
        $this->assertTrue(File::exists($logPath));
        $logContent = File::get($logPath);

        $this->assertStringContainsString('"severity_score":12', $logContent);
        $this->assertStringNotContainsString('secret-password', $logContent);
    }
}
