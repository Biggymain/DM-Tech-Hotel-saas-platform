<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use App\Casts\SupabasePgpCast;
use App\Exceptions\SecurityKeyMismatchException;
use App\Services\HardwareFingerprintService;
use App\Http\Middleware\SentryMiddleware;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DigitalFortressTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Step 1: Database Connectivity Check
     */
    public function test_supabase_connection_exists()
    {
        $connections = config('database.connections');
        $this->assertArrayHasKey('supabase', $connections);
        $this->assertEquals('pgsql', $connections['supabase']['driver']);
    }

    /**
     * Step 2: Zero-Knowledge (ZK) Encryption Test
     */
    public function test_zero_knowledge_encryption_cast()
    {
        // Mock the DB connection to simulate the RPC calls
        $mockDb = Mockery::mock('Illuminate\Database\Connection');
        DB::shouldReceive('connection')->with('supabase')->andReturn($mockDb);
        
        $mockDb->shouldReceive('selectOne')
            ->with("SELECT encrypt_sensitive_data(?, ?) as encrypted_text", ["Secret Message", "test-pass"])
            ->once()
            ->andReturn((object)['encrypted_text' => 'encrypted-blob']);

        $mockDb->shouldReceive('selectOne')
            ->with("SELECT decrypt_sensitive_data(?, ?) as plain_text", ["encrypted-blob", "test-pass"])
            ->once()
            ->andReturn((object)['plain_text' => 'Secret Message']);

        // Set the environment variable
        config(['services.supabase.passphrase' => 'test-pass']);
        
        $cast = new SupabasePgpCast();
        
        $encrypted = $cast->set(new \App\Models\User, 'name', "Secret Message", []);
        $this->assertEquals('encrypted-blob', $encrypted);

        $decrypted = $cast->get(new \App\Models\User, 'name', "encrypted-blob", []);
        $this->assertEquals('Secret Message', $decrypted);
    }

    /**
     * Step 3: Hardware Sentry - Scenario A (Valid)
     */
    public function test_sentry_middleware_scenario_active()
    {
        config(['app.env' => 'production']);
        $hash = 'test-hardware-hash';

        $mockFingerprint = Mockery::mock(HardwareFingerprintService::class);
        $mockFingerprint->shouldReceive('generateHash')->andReturn($hash);
        $this->app->instance(HardwareFingerprintService::class, $mockFingerprint);
        
        $mockDb = Mockery::mock('Illuminate\Database\Connection');
        DB::shouldReceive('connection')->with('supabase')->andReturn($mockDb);
        $mockQuery = Mockery::mock('Illuminate\Database\Query\Builder');
        
        $mockDb->shouldReceive('table')->with('devices')->andReturn($mockQuery);
        $mockQuery->shouldReceive('join')->andReturnSelf();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('select')->andReturnSelf();
        $mockQuery->shouldReceive('first')->andReturn((object)[
            'is_manually_locked' => false,
            'expires_at' => now()->addDays(10)->toDateTimeString(),
            'device_active' => true,
            'manager_email' => 'manager@hotel.com',
            'owner_email' => 'owner@hotel.com'
        ]);

        $middleware = $this->app->make(SentryMiddleware::class);
        $response = $middleware->handle(new Request(), function($req) {
            return response('OK', 200);
        });

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue(Cache::has("licensing_sentry_{$hash}"));
    }

    /**
     * Step 3: Hardware Sentry - Scenario B (Locked)
     */
    public function test_sentry_middleware_scenario_locked()
    {
        config(['app.env' => 'production']);
        $hash = 'test-hardware-hash';

        $mockFingerprint = Mockery::mock(HardwareFingerprintService::class);
        $mockFingerprint->shouldReceive('generateHash')->andReturn($hash);
        $this->app->instance(HardwareFingerprintService::class, $mockFingerprint);
        
        $mockDb = Mockery::mock('Illuminate\Database\Connection');
        DB::shouldReceive('connection')->with('supabase')->andReturn($mockDb);
        $mockQuery = Mockery::mock('Illuminate\Database\Query\Builder');
        
        $mockDb->shouldReceive('table')->with('devices')->andReturn($mockQuery);
        $mockQuery->shouldReceive('join')->andReturnSelf();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('select')->andReturnSelf();
        $mockQuery->shouldReceive('first')->andReturn((object)[
            'is_manually_locked' => true,
            'expires_at' => now()->addDays(10)->toDateTimeString(),
            'device_active' => true,
            'manager_email' => 'manager@hotel.com',
            'owner_email' => 'owner@hotel.com'
        ]);

        $middleware = $this->app->make(SentryMiddleware::class);
        $response = $middleware->handle(new Request(), function($req) {
            return response('OK', 200);
        });

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Branch manually locked', $response->getContent());
    }

    /**
     * Step 3: Hardware Sentry - Scenario C (Unregistered)
     */
    public function test_sentry_middleware_scenario_unregistered()
    {
        config(['app.env' => 'production']);
        $hash = 'unknown-hardware-hash';

        $mockFingerprint = Mockery::mock(HardwareFingerprintService::class);
        $mockFingerprint->shouldReceive('generateHash')->andReturn($hash);
        $this->app->instance(HardwareFingerprintService::class, $mockFingerprint);
        
        $mockDb = Mockery::mock('Illuminate\Database\Connection');
        DB::shouldReceive('connection')->with('supabase')->andReturn($mockDb);
        $mockQuery = Mockery::mock('Illuminate\Database\Query\Builder');
        
        $mockDb->shouldReceive('table')->with('devices')->andReturn($mockQuery);
        $mockQuery->shouldReceive('join')->andReturnSelf();
        $mockQuery->shouldReceive('where')->andReturnSelf();
        $mockQuery->shouldReceive('select')->andReturnSelf();
        $mockQuery->shouldReceive('first')->andReturn(null); // No device found

        $middleware = $this->app->make(SentryMiddleware::class);
        $response = $middleware->handle(new Request(), function($req) {
            return response('OK', 200);
        });

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Hardware Not Registered', $response->getContent());
    }

    /**
     * Scenario 6: Branch Creation Sync Logic
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_branch_creation_syncs_to_supabase()
    {
        // Mock DB connection for Supabase specifically to intercept the sync write
        $mockSupabase = Mockery::mock('Illuminate\Database\Connection')->makePartial();
        $mockQuery = Mockery::mock('Illuminate\Database\Query\Builder');
        
        $mockSupabase->shouldReceive('table')->with('branches')->andReturn($mockQuery);

        // Expect an insert into Supabase with tiered expiry
        $mockQuery->shouldReceive('insert')->once()->with(Mockery::on(function($data) {
            return $data['manager_email'] === 'test@hotel.com' && 
                   $data['expires_at'] > now()->addDays(29);
        }))->andReturn(true);

        DB::extend('supabase', function ($config, $name) use ($mockSupabase) {
            return $mockSupabase;
        });
        config(['database.connections.supabase' => ['driver' => 'pgsql']]);

        // Trigger branch creation via service
        $service = new \App\Services\GroupRegistrationService();
        $group = \App\Models\HotelGroup::create([
            'name' => 'Test Group',
            'slug' => 'test-group',
            'contact_email' => 'group@hotel.com',
            'currency' => 'USD',
            'tax_rate' => 0,
            'is_active' => true,
        ]);
        
        $service->createBranch($group, [
            'name' => 'Test Branch',
            'email' => 'test@hotel.com',
            'tier' => 'basic'
        ]);
        
        $this->assertTrue(true);
    }

    /**
     * Scenario 7: Vault Admin Handshake Command
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_vault_admin_command_handshake()
    {
        config([
            'services.supabase.passphrase' => 'fortress-pass',
            'app.super_admin_email' => 'admin@fortress.tech',
            'app.super_admin_password' => 'admin-pass',
        ]);
        
        $hardwareHash = 'test-hardware-hash';
        $mockFingerprint = Mockery::mock(HardwareFingerprintService::class);
        $mockFingerprint->shouldReceive('generateHash')->andReturn($hardwareHash);
        $this->app->instance(HardwareFingerprintService::class, $mockFingerprint);

        // Mock DB connection for Supabase
        $mockDb = Mockery::mock('Illuminate\Database\Connection');
        DB::shouldReceive('connection')->with('supabase')->andReturn($mockDb);

        // Predict the random string for the Pre-Flight test dynamically by capturing it
        $capturedTestString = '';
        
        // 0. Pre-Flight Mock
        $mockDb->shouldReceive('selectOne')
            ->withArgs(function($query, $args) use (&$capturedTestString) {
                if (str_contains($query, 'encrypt_sensitive_data') && str_starts_with($args[0] ?? '', 'FORTRESS_TEST_VAL_')) {
                    $capturedTestString = $args[0];
                    return true;
                }
                return false;
            })
            ->once()
            ->andReturn((object)['encrypted' => 'test-blob']);

        $mockDb->shouldReceive('selectOne')
            ->withArgs(function($query, $args) {
                return str_contains($query, 'decrypt_sensitive_data') && ($args[0] ?? '') === 'test-blob';
            })
            ->once()
            ->andReturnUsing(function($query, $args) use (&$capturedTestString) {
                return (object)['plain_text' => $capturedTestString];
            });

        // 1. Main Encryption Mock
        $mockDb->shouldReceive('selectOne')
            ->with("SELECT encrypt_sensitive_data(?, ?) as encrypted", ["admin-pass", "fortress-pass"])
            ->once()
            ->andReturn((object)['encrypted' => 'pgp-blob-123']);

        // 2. Storage Mock
        $mockQuery = Mockery::mock('Illuminate\Database\Query\Builder');
        $mockDb->shouldReceive('table')->with('users')->andReturn($mockQuery);
        $mockQuery->shouldReceive('updateOrInsert')->once()->andReturn(true);

        // 3. Handshake Mock - Model Decryption
        // Mocking Eloquent query on the 'supabase' connection
        $mockUser = Mockery::mock('overload:App\Models\User');
        $mockUser->shouldReceive('on')->with('supabase')->andReturnSelf();
        $mockUser->shouldReceive('where')->with('email', 'admin@fortress.tech')->andReturnSelf();
        $mockUser->shouldReceive('first')->andReturn($mockUser);
        $mockUser->shouldReceive('getAttribute')->andReturnUsing(function($attr) use ($hardwareHash) {
            return match($attr) {
                'password' => 'admin-pass',
                'hardware_hash' => $hardwareHash,
                default => null
            };
        });
        $mockUser->password = 'admin-pass';
        $mockUser->hardware_hash = $hardwareHash;
        $mockUser->shouldReceive('getConnectionName')->andReturn('supabase');

        $this->artisan('fortress:vault-admin')
            ->expectsOutput('Starting Digital Fortress: Vault Admin Handshake...')
            ->expectsOutput("Identifying User: admin@fortress.tech")
            ->expectsOutput("Hardware ID: {$hardwareHash}")
            ->expectsOutput('Running Pre-Flight Handshake Test...')
            ->expectsOutput('✅ Pre-flight Handshake: SUCCESS')
            ->expectsOutput('Encrypting credentials via hardware-locked RPC...')
            ->expectsOutput("Storing 'De-identified' Identity in Supabase Vault...")
            ->expectsOutput('Verifying Local-to-Vault Handshake...')
            ->expectsOutput('----------------------------------------------------')
            ->expectsOutput('🔒 DIGITAL FORTRESS HANDSHAKE SUCCESSFUL')
            ->expectsOutput('----------------------------------------------------')
            ->expectsOutput('The software has successfully de-identified the Super Admin.')
            ->expectsOutput('Hardware Marriage: CONFIRMED')
            ->expectsOutput('Cloud Vault Verification: CONFIRMED')
            ->expectsConfirmation('Handshake successful. Do you want to finalize de-identification by removing credentials from .env?', 'no')
            ->assertExitCode(0);
    }

    /**
     * Scenario 8: System Lockdown (Kill-Switch)
     */
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
    public function test_login_hardware_marriage_enforcement()
    {
        $hardwareHash = 'authorized-device-id';
        // Mock Supabase DB properly without breaking the facade root
        $mockSupabase = Mockery::mock('Illuminate\Database\Connection')->makePartial();
        $mockSupabase->shouldReceive('selectOne')->andReturn((object)['encrypted_text' => 'blob', 'plain_text' => 'password']);

        // Only override the 'supabase' connection
        DB::extend('supabase', function ($config, $name) use ($mockSupabase) {
            return $mockSupabase;
        });
        config(['database.connections.supabase' => ['driver' => 'pgsql']]);

        $user = \App\Models\User::factory()->create([
            'email' => 'group@admin.com',
            'is_super_admin' => true,
            'hardware_hash' => $hardwareHash
        ]);

        // Mock fingerprint service to return a MISMATCHED hash
        $mockFingerprint = Mockery::mock(HardwareFingerprintService::class);
        $mockFingerprint->shouldReceive('generateHash')->andReturn('malicious-device-id');
        $this->app->instance(HardwareFingerprintService::class, $mockFingerprint);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'group@admin.com',
            'password' => 'password'
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('Hardware Handshake Mismatch', $response->getContent());
    }

    /**
     * Scenario 10: Recovery Command (Unlock)
     */
    public function test_fortress_unlock_command()
    {
        config(['services.supabase.passphrase' => 'master-secret']);
        $lockService = $this->app->make(\App\Services\FortressLockService::class);
        $lockService->triggerLock();
        $this->assertTrue($lockService->isLocked());

        // Test invalid key
        $this->artisan('fortress:unlock', ['--master-key' => 'wrong-key'])
            ->expectsOutput('!!! INVALID RECOVERY KEY !!!')
            ->assertExitCode(1);

        // Test valid key
        $this->artisan('fortress:unlock', ['--master-key' => 'master-secret'])
            ->expectsOutput('🔓 DIGITAL FORTRESS LOCK RELEASED')
            ->assertExitCode(0);

        $this->assertFalse($lockService->isLocked());
    }
}
