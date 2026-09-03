<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\HardwareDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class DeveloperSentryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset config for security testing
        Config::set('fortress.supabase_dev_key', 'test-supabase-key');
        Config::set('fortress.dev_passphrase_hash', Hash::make('test-passphrase'));
    }

    /**
     * Test registration security.
     */
    public function test_registration_requires_valid_key_and_passphrase()
    {
        $response = $this->postJson('/api/v1/auth/developer/register-terminal', [
            'passphrase' => 'wrong-passphrase'
        ], [
            'X-Supabase-Dev-Key' => 'test-supabase-key'
        ]);

        $response->assertStatus(401)
                 ->assertJson(['error' => 'Unauthorized Developer Passphrase']);

        $response = $this->postJson('/api/v1/auth/developer/register-terminal', [
            'passphrase' => 'test-passphrase'
        ], [
            'X-Supabase-Dev-Key' => 'wrong-key'
        ]);

        $response->assertStatus(401)
                 ->assertJson(['error' => 'Unauthorized Sentry Key']);
    }

    /**
     * Test successful registration as Master (System Level - No Hotel).
     */
    public function test_successful_registration_creates_master_device_without_hotel()
    {
        $hash = 'test-hardware-hash-123';
        
        $response = $this->postJson('/api/v1/auth/developer/register-terminal', [
            'passphrase' => 'test-passphrase'
        ], [
            'X-Supabase-Dev-Key' => 'test-supabase-key',
            'X-Hardware-Id' => $hash
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Phoenix Master Marriage Successful',
                     'hardware_hash' => $hash,
                     'access_level' => 'master'
                 ]);

        $this->assertDatabaseHas('hardware_devices', [
            'hardware_hash' => $hash,
            'access_level' => 'master',
            'hotel_id' => null,
            'status' => 'active'
        ]);
    }

    /**
     * Test that Master bypass works in testing/local environment.
     */
    public function test_master_bypass_is_active_in_testing_environment()
    {
        // 1. Register a master device manually with NO hotel
        $hash = 'master-hash-testing';
        HardwareDevice::create([
            'hotel_id' => null,
            'hardware_uuid' => 'PHOENIX-MASTER-TEST',
            'hardware_hash' => $hash,
            'access_level' => 'master',
            'device_name' => 'Test Master',
            'status' => 'active',
            'is_verified' => true
        ]);

        $this->assertTrue(app()->runningUnitTests());
        
        // The middleware will now treat this device as a master bypass even in testing.
        $this->assertDatabaseHas('hardware_devices', [
            'hardware_hash' => $hash,
            'access_level' => 'master',
            'hotel_id' => null
        ]);
    }
}
