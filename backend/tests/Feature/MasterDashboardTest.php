<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\HardwareDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MasterDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $masterAdmin;
    protected $hotel;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $masterHash = self::generateMockHardwareHash();

        // Create a Hotel
        $this->hotel = \App\Models\Hotel::factory()->create();

        // Create a Super Admin
        $this->masterAdmin = User::factory()->create([
            'hotel_id' => $this->hotel->id,
            'is_super_admin' => true,
            'hardware_hash' => $masterHash,
            'is_approved' => true,
        ]);

        // Register the hardware as Master
        HardwareDevice::create([
            'device_name' => 'Master Terminal',
            'hardware_uuid' => 'master-uuid',
            'hardware_hash' => $masterHash,
            'access_level' => 'master',
            'status' => 'active',
        ]);
    }

    /** @test */
    public function test_master_can_check_developer_status()
    {
        $response = $this->actingAs($this->masterAdmin)
            ->withHeaders([
                'X-Frontend-Port' => '3000',
            ])
            ->getJson('/api/v1/auth/developer/status');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'verified',
                'access_level' => 'master',
            ]);
    }

    /** @test */
    public function test_master_can_view_siem_alerts()
    {
        // Create some SIEM alerts in AuditLog
        AuditLog::create([
            'hotel_id' => $this->hotel->id,
            'user_id' => $this->masterAdmin->id,
            'entity_type' => 'user',
            'entity_id' => $this->masterAdmin->id,
            'change_type' => 'hardware_mismatch',
            'reason' => 'Critical hardware mismatch detected',
            'source' => 'api'
        ]);

        $response = $this->actingAs($this->masterAdmin)
            ->withHeaders([
                'X-Frontend-Port' => '3000',
            ])
            ->getJson('/api/v1/auth/siem/alerts');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'message' => 'Critical hardware mismatch detected',
                'severity' => 12,
            ]);
    }

    /** @test */
    public function test_non_master_hardware_cannot_access_status()
    {
        $otherAdmin = User::factory()->create([
            'is_super_admin' => true,
            'hardware_hash' => 'other-hash',
        ]);

        // Hardware is registered but not as master
        HardwareDevice::create([
            'hardware_hash' => 'other-hash',
            'device_name' => 'Other Staff Terminal',
            'hardware_uuid' => 'STAFF-TEST',
            'access_level' => 'staff',
            'status' => 'active',
            'is_verified' => true,
            'expires_at' => now()->addYear(),
        ]);

        $response = $this->actingAs($otherAdmin)
            ->withHeaders([
                'X-Hardware-Id' => 'other-hash',
                'X-Frontend-Port' => '3000',
            ])
            ->getJson('/api/v1/auth/developer/status');

        $response->assertStatus(403);
    }
}
