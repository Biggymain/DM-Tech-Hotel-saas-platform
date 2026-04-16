<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Hotel;
use App\Models\Outlet;
use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Services\ActivityLogService;
use App\Services\AuditLogService;
use App\Jobs\CleanOldLogsJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

class MonitoringSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $hotelA;
    protected $hotelB;
    protected $userA;
    protected $userB;
    protected $outletA;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->withoutMiddleware(\App\Http\Middleware\RoleVerificationMiddleware::class); // We will explicitly test Role Middleware logic or bypass initially for raw tests

        $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);

        $this->hotelA = Hotel::create(['name' => 'Monitoring Hotel A']);
        $this->userA = User::factory()->create(['hotel_id' => $this->hotelA->id, 'is_super_admin' => true]);
        $roleA = \App\Models\Role::withoutGlobalScopes()->where('slug', 'hotelowner')->first();
        if ($roleA) { $this->userA->roles()->attach($roleA->id); }

        $this->outletA = Outlet::create(['hotel_id' => $this->hotelA->id, 'name' => 'Outlet A']);

        $this->hotelB = Hotel::create(['name' => 'Monitoring Hotel B']);
        $this->userB = User::factory()->create(['hotel_id' => $this->hotelB->id, 'is_super_admin' => true]);
        $roleB = \App\Models\Role::withoutGlobalScopes()->where('slug', 'hotelowner')->first();
        if ($roleB) { $this->userB->roles()->attach($roleB->id); }
    }

    public function test_activity_log_service_creates_records_with_outlet_and_severity()
    {
        $service = new ActivityLogService();
        $log = $service->logAction(
            hotelId: $this->hotelA->id,
            action: 'test_action_1',
            description: 'Testing severity and outlet',
            outletId: $this->outletA->id,
            severity: 'critical'
        );

        $this->assertInstanceOf(ActivityLog::class, $log);
        $this->assertDatabaseHas('activity_logs', [
            'hotel_id' => $this->hotelA->id,
            'outlet_id' => $this->outletA->id,
            'action' => 'test_action_1',
            'severity' => 'critical'
        ]);
    }

    public function test_audit_log_service_creates_records_with_source_tracking()
    {
        $service = new AuditLogService();
        
        $invoice = Invoice::create([
            'hotel_id' => $this->hotelA->id,
            'invoice_number' => 'INV-MONITOR-01',
            'sequence_number' => 1,
            'subtotal' => 100,
            'tax_amount' => 10,
            'total_amount' => 110,
            'status' => 'pending',
            'currency_code' => 'USD'
        ]);

        $log = $service->recordChange(
            hotelId: $this->hotelA->id,
            entityType: Invoice::class,
            entityId: $invoice->id,
            changeType: 'updated',
            oldValues: ['status' => 'pending'],
            newValues: ['status' => 'paid'],
            source: 'api'
        );

        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertDatabaseHas('audit_logs', [
            'hotel_id' => $this->hotelA->id,
            'entity_type' => Invoice::class,
            'entity_id' => $invoice->id,
            'change_type' => 'updated',
            'source' => 'api'
        ]);
        
        // Assert JSON values are correctly encoded
        $dbLog = AuditLog::find($log->id);
        $this->assertEquals('pending', $dbLog->old_values['status']);
        $this->assertEquals('paid', $dbLog->new_values['status']);
    }

    public function test_api_endpoints_support_filtering()
    {
        ActivityLog::create([
            'hotel_id' => $this->hotelA->id,
            'action' => 'targeted_action',
            'severity' => 'critical',
            'user_id' => $this->userA->id
        ]);
        
        ActivityLog::create([
            'hotel_id' => $this->hotelA->id,
            'action' => 'other_action',
            'severity' => 'info',
            'user_id' => $this->userA->id
        ]);

        $response = $this->actingAs($this->userA)->getJson('/api/v1/system/activity-logs?action=targeted_action&severity=critical');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('targeted_action', $response->json('data.0.action'));
    }

    public function test_tenant_isolation_prevents_cross_hotel_log_access()
    {
        // Add log to Hotel A
        ActivityLog::create([
            'hotel_id' => $this->hotelA->id,
            'action' => 'hotel_a_action',
            'severity' => 'info',
        ]);
        
        // Add log to Hotel B
        ActivityLog::create([
            'hotel_id' => $this->hotelB->id,
            'action' => 'hotel_b_action',
            'severity' => 'info',
        ]);

        // User B fetches logs, should ONLY see hotel_b_action
        $response = $this->actingAs($this->userB)->getJson('/api/v1/system/activity-logs');
        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('hotel_b_action', $response->json('data.0.action'));
    }

    public function test_clean_old_logs_job_properly_deletes_expired_logs()
    {
        \Illuminate\Support\Facades\DB::table('activity_logs')->insert([
            ['hotel_id' => $this->hotelA->id, 'action' => 'recent_activity', 'created_at' => Carbon::now()->subDays(10), 'updated_at' => Carbon::now()->subDays(10)],
            ['hotel_id' => $this->hotelA->id, 'action' => 'old_activity', 'created_at' => Carbon::now()->subDays(100), 'updated_at' => Carbon::now()->subDays(100)]
        ]);

        \Illuminate\Support\Facades\DB::table('audit_logs')->insert([
            ['hotel_id' => $this->hotelA->id, 'entity_type' => 'Invoice', 'entity_id' => 1, 'change_type' => 'created', 'created_at' => Carbon::now()->subDays(100), 'updated_at' => Carbon::now()->subDays(100)],
            ['hotel_id' => $this->hotelA->id, 'entity_type' => 'Invoice', 'entity_id' => 2, 'change_type' => 'created', 'created_at' => Carbon::now()->subDays(400), 'updated_at' => Carbon::now()->subDays(400)]
        ]);

        // Execute Job
        (new CleanOldLogsJob())->handle();

        $this->assertDatabaseHas('activity_logs', ['action' => 'recent_activity']);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'old_activity']);

        $this->assertDatabaseHas('audit_logs', ['entity_id' => 1]);
        $this->assertDatabaseMissing('audit_logs', ['entity_id' => 2]);
    }
}
