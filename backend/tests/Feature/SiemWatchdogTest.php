<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use App\Services\HardwareFingerprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class SiemWatchdogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test high-severity correlation: Hardware Mismatch + Port Violation
     * result in automatic account suspension (is_approved = false).
     */
    public function test_watchdog_suspends_user_on_severity_threshold()
    {
        // 1. Setup a legit Group Admin user
        $user = User::factory()->create([
            'email' => 'group-admin@test.com',
            'is_approved' => true,
            'hardware_hash' => 'valid-hardware-hash'
        ]);

        $this->mock(HardwareFingerprintService::class, function ($mock) {
            $mock->shouldReceive('generateHash')->andReturn('valid-hardware-hash');
        });

        $role = Role::create(['name' => 'groupadmin', 'slug' => 'groupadmin']);
        $user->roles()->attach($role->id);
        $user->load('roles');

        Config::set('fortress.port_mapping', [
            'groupadmin' => 3001,
        ]);

        // 2. Trigger Hardware Mismatch (Severity 12)
        // Ensure we are using the actingAs user on the correct port to avoid early 404
        $this->withPort(3001)->actingAs($user)
            ->withHeaders(['X-Hardware-Id' => 'malicious-device-id'])
            ->getJson('/api/v1/auth/me')
            ->assertStatus(403);

        // Verify the FIRST audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'change_type' => 'hardware_mismatch'
        ]);

        // 3. Trigger Port Violation (Severity 12)
        $this->actingAs($user)
            ->withHeaders([
                'X-Frontend-Port' => '3000',
                'X-Hardware-Id' => 'valid-hardware-hash'
            ])
            ->getJson('/api/v1/auth/me')
            ->assertStatus(404);

        // Verify the SECOND audit log exists
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'change_type' => 'port_violation'
        ]);

        // 4. Verify user is suspended by AuditLogObserver's correlation rule
        $user->refresh();
        $this->assertFalse($user->is_approved, "User should be automatically suspended after correlated SIEM violations.");
    }
}
