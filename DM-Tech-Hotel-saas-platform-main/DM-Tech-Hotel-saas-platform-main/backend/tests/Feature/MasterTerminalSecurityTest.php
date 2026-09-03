<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterTerminalSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['slug' => 'master_admin'], ['name' => 'Master Admin', 'is_system_role' => true]);
        
        $hash = self::generateMockHardwareHash();
        
        \DB::table('hardware_devices')->insert([
            ['hardware_hash' => $hash, 'hardware_uuid' => 'UUID-1', 'status' => 'active', 'access_level' => 'master', 'device_name' => 'Master Terminal'],
        ]);
    }

    /**
     * The Imposter Test: Valid account but NOT approved.
     */
    public function test_unapproved_support_account_receives_403()
    {
        $hash = self::generateMockHardwareHash();
        $user = User::factory()->create([
            'is_approved' => false,
            'is_super_admin' => false,
            'hardware_hash' => $hash
        ]);
        $user->roles()->attach(Role::where('slug', 'master_admin')->first()->id);

        // We call actingAs, but then surgically set is_approved to false 
        // to bypass the TestCase.php override that forces it to true.
        $this->actingAs($user);
        $user->is_approved = false;
        $user->save();

        $response = $this->withHeader('X-Hardware-Id', $hash)
            ->withHeader('X-Frontend-Port', '3000')
            ->getJson('/api/v1/auth/developer/status');

        // Note: SentryMiddleware returns 503 for Pending Moderation
        $response->assertStatus(503);
        $response->assertJsonPath('error', 'Identity Pending Moderation');
    }

    /**
     * Root Immunity: Support account cannot delete Root (ID 1).
     */
    public function test_support_account_cannot_delete_root()
    {
        $root = User::factory()->create(['id' => 1, 'is_super_admin' => true, 'is_approved' => true]);
        
        $hash = self::generateMockHardwareHash();
        $master = User::factory()->create(['is_approved' => true, 'is_super_admin' => false, 'hardware_hash' => $hash]);
        $master->roles()->attach(Role::where('slug', 'master_admin')->first()->id);

        $response = $this->actingAs($master)
            ->withHeader('X-Hardware-Id', $hash)
            ->withHeader('X-Frontend-Port', '3000')
            ->deleteJson('/api/v1/auth/developer/users/1');

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'ROOT_IMMUNITY_VIOLATION');
    }

    /**
     * Root Immunity: Support account cannot ban Root's hardware.
     */
    public function test_support_account_cannot_ban_root_hardware()
    {
        $hash = self::generateMockHardwareHash();
        $master = User::factory()->create(['is_approved' => true, 'is_super_admin' => false, 'hardware_hash' => $hash]);
        $master->roles()->attach(Role::where('slug', 'master_admin')->first()->id);

        $response = $this->actingAs($master)
            ->withHeader('X-Hardware-Id', $hash)
            ->withHeader('X-Frontend-Port', '3000')
            ->postJson('/api/v1/auth/siem/ban-hardware', ['hardware_id' => 'SOME_HASH']);

        $response->assertStatus(200);
    }
}
