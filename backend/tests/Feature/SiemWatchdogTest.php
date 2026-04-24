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
use PHPUnit\Framework\Attributes\Test;

class SiemWatchdogTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test high-severity correlation: Hardware Mismatch + Port Violation
     * result in automatic account suspension (is_approved = false).
     */
    #[Test]
    public function test_watchdog_suspends_user_on_severity_threshold()
    {
        // 1. Setup a legit Group Admin user
        $user = User::factory()->create([
            'email' => 'group-admin@test.com',
            'is_approved' => true,
            'hardware_hash' => \Illuminate\Support\Facades\Hash::make('different_device')
        ]);

        $this->mock(HardwareFingerprintService::class, function ($mock) {
            $mock->shouldReceive('generateHash')->andReturn(\Tests\TestCase::generateMockHardwareHash());
        });

        $role = Role::create(['name' => 'groupadmin', 'slug' => 'groupadmin']);
        $user->roles()->attach($role->id);
        $user->load('roles');

        Config::set('fortress.port_mapping', [
            'groupadmin' => 3001,
        ]);

        // 2. Trigger Hardware Mismatch (Severity 12)
        // Ensure we are using the actingAs user on the correct port to avoid early 404
        $this->actingAs($user);
        
        $user->forceFill([
            'hardware_hash' => \Illuminate\Support\Facades\Hash::make('different_device')
        ])->save();

        $this->withPort(3001)
            ->withHeaders(['X-Hardware-Id' => \Tests\TestCase::generateMockHardwareHash()])
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
                'X-Hardware-Id' => \Tests\TestCase::generateMockHardwareHash()
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

    #[Test]
    public function test_siem_logs_are_stored_encrypted_in_database()
    {
        $hash = \Tests\TestCase::generateMockHardwareHash();
        
        // Generate an audit log
        AuditLogService::log(
            'user', 1, 'security_event',
            ['old' => 'data'],
            ['attempted' => $hash, 'hash' => $hash],
            'Testing encryption at rest',
            'api'
        );

        // 1. Verify raw database storage is encrypted
        $rawLog = \Illuminate\Support\Facades\DB::table('audit_logs')->first();
        
        $this->assertNotNull($rawLog);
        $this->assertEquals($hash, $rawLog->hardware_id, "Hardware ID should be indexed as plain text.");
        $this->assertStringStartsWith('eyJpdiI6', $rawLog->reason, "Reason should be an encrypted payload.");
        $this->assertStringStartsWith('eyJpdiI6', $rawLog->new_values, "New Values should be an encrypted payload.");
        $this->assertStringNotContainsString('Testing encryption at rest', $rawLog->reason);

        // 2. Verify Eloquent automatically decrypts for the UI/Controllers
        $modelLog = AuditLog::first();
        
        $this->assertEquals('Testing encryption at rest', $modelLog->reason);
        $this->assertIsArray($modelLog->new_values);
        $this->assertEquals($hash, $modelLog->new_values['attempted']);
    }
}
