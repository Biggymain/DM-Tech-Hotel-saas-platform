<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ActiveResponseTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        
        $this->hotel = Hotel::factory()->create();
        $this->user = User::factory()->create(['hotel_id' => $this->hotel->id, 'is_approved' => true]);
    }

    /**
     * Step A: Verify Correlated Attack Detection (60s window)
     */
    public function test_detects_correlated_attack_within_60_seconds()
    {
        Log::shouldReceive('channel')->with('siem')->andReturnSelf();
        Log::shouldReceive('alert')->withArgs(fn($msg, $context) => 
            $msg === 'Correlated Attack Detected: Multi-High-Severity Event Chain.' &&
            $context['severity_score'] === 14
        )->once();

        // Also expect the legacy emergency suspension log because these two events 
        // trigger the 5-minute watchdog rule as well.
        Log::shouldReceive('emergency')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        // Trigger first high-severity event
        AuditLogService::log(
            'user', $this->user->id, 'port_violation',
            [], [], 'First breach', 'api', $this->hotel->id, $this->user->id
        );

        // Trigger second high-severity event within 60s
        AuditLogService::log(
            'user', $this->user->id, 'hardware_mismatch',
            [], [], 'Second breach', 'api', $this->hotel->id, $this->user->id
        );

        $this->assertTrue(true); // Explicit assertion to resolve risky test status
    }

    /**
     * Step B: Verify Neutrality Gate (Severity < 12 Return Immediately)
     */
    public function test_neutrality_gate_ignores_low_severity_events()
    {
        Log::shouldReceive('channel')->with('siem')->andReturnSelf();
        Log::shouldReceive('alert')->never();

        // Trigger low-severity event (e.g. order_created is not in SEVERITY_MAP)
        AuditLogService::log(
            'order', 1, 'order_created',
            [], [], 'Standard business action', 'api', $this->hotel->id, $this->user->id
        );

        // Even two low-severity events should not trigger correlation
        AuditLogService::log(
            'order', 1, 'order_created',
            [], [], 'Another standard action', 'api', $this->hotel->id, $this->user->id
        );

        $this->assertTrue(true); // Explicit assertion to resolve risky test status
    }

    /**
     * Step C: Verify Indestructible Recursion Guard
     */
    public function test_recursion_protection_prevents_infinite_loops()
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('alert')->andReturnNull();
        Log::shouldReceive('emergency')->andReturnNull();
        Log::shouldReceive('info')->andReturnNull();

        $initialCount = AuditLog::count();
        
        AuditLogService::log(
            'user', $this->user->id, 'watchdog_suspension',
            [], [], 'Watchdog trigger', 'system', $this->hotel->id, $this->user->id
        );

        $this->assertEquals($initialCount + 1, AuditLog::count());
    }

    /**
     * Step D: Verify No Correlation Outside 60s Window
     */
    public function test_does_not_correlate_events_outside_60_second_window()
    {
        Log::shouldReceive('channel')->with('siem')->andReturnSelf();
        Log::shouldReceive('alert')->never();

        // Create a log entry from 2 minutes ago
        AuditLog::factory()->create([
            'change_type' => 'port_violation',
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subMinutes(2),
            'user_id' => $this->user->id
        ]);

        // Trigger new event now
        AuditLogService::log(
            'user', $this->user->id, 'port_violation',
            [], [], 'New breach', 'api', $this->hotel->id, $this->user->id
        );

        $this->assertTrue(true); // Explicit assertion to resolve risky test status
    }

    /**
     * Step E: Identify Auto-Lock Enforcement (Active Response)
     */
    public function test_attacker_is_automatically_suspended()
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('alert')->andReturnNull();
        Log::shouldReceive('emergency')->withArgs(fn($msg, $context) => 
            str_contains($msg, 'Automated Lockdown Initiated') &&
            $context['severity_score'] === 15 &&
            $context['user_id'] === $this->user->id
        )->once();

        // Sanity Check: User is approved
        $this->assertTrue($this->user->fresh()->is_approved);

        // Chain of high-severity events that trigger correlation
        AuditLogService::log('user', $this->user->id, 'port_violation', [], [], 'Breach 1', 'api', $this->hotel->id, $this->user->id);
        AuditLogService::log('user', $this->user->id, 'port_violation', [], [], 'Breach 2', 'api', $this->hotel->id, $this->user->id);

        // Final Validation: User must be suspended (is_approved = false)
        $this->assertFalse($this->user->fresh()->is_approved);
    }
}
