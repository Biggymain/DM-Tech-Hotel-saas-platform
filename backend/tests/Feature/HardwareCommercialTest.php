<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class HardwareCommercialTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $manager;
    protected $waitress;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::factory()->create(['device_slots' => 2]);
        
        $this->manager = User::factory()->create([
            'hotel_id' => $this->hotel->id,
            'email' => 'manager@hotel.com'
        ]);
        $managerRole = Role::create(['name' => 'General Manager', 'slug' => 'generalmanager']);
        $this->manager->roles()->attach($managerRole->id);

        $this->waitress = User::factory()->create([
            'hotel_id' => $this->hotel->id,
            'email' => 'waitress@hotel.com'
        ]);
        $waitressRole = Role::create(['name' => 'Waitress', 'slug' => 'waitress']);
        $this->waitress->roles()->attach($waitressRole->id);

        // Seed Supabase Mock
        DB::connection('supabase')->table('branches')->insert([
            'id' => $this->hotel->id,
            'branch_token' => (string) Str::uuid(),
            'is_active' => 1,
            'expires_at' => now()->addYear()
        ]);
    }

    #[Test]
    public function test_branch_activation_respects_device_slots()
    {
        $branchToken = DB::connection('supabase')->table('branches')->first()->branch_token;

        // Fill up slots (2 slots)
        DB::connection('supabase')->table('devices')->insert([
            ['branch_id' => $this->hotel->id, 'hardware_hash' => 'hash1', 'is_active' => 1],
            ['branch_id' => $this->hotel->id, 'hardware_hash' => 'hash2', 'is_active' => 1],
        ]);

        // Try to activate a 3rd device
        $response = $this->postJson('/api/v1/auth/activate-branch', [
            'branch_token' => $branchToken,
            'hardware_id' => 'hash3'
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'Branch device limit reached.']);
    }

    #[Test]
    public function test_staff_roles_cannot_request_hardware_access()
    {
        $response = $this->postJson('/api/v1/auth/request-hardware-access', [
            'email' => 'waitress@hotel.com'
        ]);

        $response->assertStatus(403);
        $response->assertJsonFragment(['error_code' => 'staff_lockdown']);
    }

    #[Test]
    public function test_manager_relink_purges_old_supabase_signature()
    {
        $oldHash = 'old-manager-hash';
        $this->manager->update(['hardware_hash' => $oldHash]);

        // Record exists in Supabase
        DB::connection('supabase')->table('devices')->insert([
            'branch_id' => $this->hotel->id,
            'hardware_hash' => $oldHash,
            'is_active' => 1
        ]);

        $this->assertEquals(1, DB::connection('supabase')->table('devices')->where('hardware_hash', $oldHash)->count());

        // Manager requests relink
        $this->postJson('/api/v1/auth/request-hardware-access', [
            'email' => 'manager@hotel.com'
        ])->assertStatus(200);

        $this->manager->refresh();
        $this->assertTrue($this->manager->is_relinking);
        $this->assertEquals($oldHash, $this->manager->hardware_hash);

        // Finalize marriage (One-Out, One-In happens here)
        $service = app(\App\Services\HardwareMarriageService::class);
        $service->marry($this->manager, 'new-manager-hash');

        // Verify old signature is purged from Supabase
        $this->assertEquals(0, DB::connection('supabase')->table('devices')->where('hardware_hash', $oldHash)->count());
    }
}
