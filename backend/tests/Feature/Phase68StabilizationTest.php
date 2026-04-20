<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Hotel;
use App\Models\HotelGroup;
use App\Models\HotelSubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\InventoryItem;
use App\Models\StockTransfer;
use App\Models\GatewaySetting;
use App\Models\Department;
use App\Services\HardwareValidationService;
use App\Services\FortressLockService;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use Mockery\MockInterface;

class Phase68StabilizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Globally mock HardwareValidationService
        $hwMock = \Mockery::mock(HardwareValidationService::class);
        $hwMock->shouldReceive('validate')->andReturn([
            'is_manually_locked' => false,
            'expires_at' => now()->addYear()->toDateTimeString(),
            'device_active' => true,
        ]);
        app()->instance(HardwareValidationService::class, $hwMock);

        // Globally mock FortressLockService
        $lockMock = \Mockery::mock(FortressLockService::class);
        $lockMock->shouldReceive('isLocked')->andReturn(false);
        app()->instance(FortressLockService::class, $lockMock);

        // Globally mock PermissionService to always allow
        $permMock = \Mockery::mock(PermissionService::class);
        $permMock->shouldReceive('hasPermission')->andReturn(true);
        app()->instance(PermissionService::class, $permMock);
    }

    /**
     * Scenario X: Confirm Encryption at rest for Monnify keys.
     */
    public function test_scenario_x_encryption_at_rest()
    {
        $hotel = Hotel::factory()->create();
        $setting = GatewaySetting::create([
            'hotel_id' => $hotel->id,
            'gateway_name' => 'monnify',
            'api_key' => 'MK_TEST_123',
            'secret_key' => 'SECRET_456',
            'is_active' => true,
        ]);

        $this->assertEquals('MK_TEST_123', $setting->api_key);
        $raw = \Illuminate\Support\Facades\DB::table('gateway_settings')->where('id', $setting->id)->first();
        $this->assertNotEquals('MK_TEST_123', $raw->api_key);
    }

    /**
     * Scenario Y: Confirm POST is blocked but GET is allowed on suspended branches.
     */
    public function test_scenario_y_passive_guard_enforcement()
    {
        $group = HotelGroup::create([
            'name' => 'Test Group',
            'slug' => 'test-group',
            'is_active' => true,
        ]);
        $slug = 'plan_' . uniqid();
        $plan = SubscriptionPlan::create([
            'name' => 'Pro Plan',
            'slug' => $slug,
            'features' => ['pms', 'inventory', 'analytics', 'rooms.create', 'hotel.manage'],
            'price' => 50000,
            'is_active' => true,
        ]);

        $hotel = Hotel::factory()->create([
            'hotel_group_id' => $group->id,
            'subscription_plan_id' => $plan->id
        ]);
        
        HotelSubscription::factory()->create([
            'hotel_id' => $hotel->id,
            'plan_id' => $plan->id,
            'status' => 'suspended',
        ]);

        $admin = User::factory()->create([
            'hotel_id' => $hotel->id,
            'hotel_group_id' => $group->id,
            'role' => 'admin',
            'is_approved' => true,
            'hardware_hash' => null,
        ]);

        $headers = [
            'X-Hardware-Id' => 'HW_STAB',
            'X-Test-Verify-Subscription' => 'true',
            'X-Tenant-ID' => $hotel->id,
        ];

        // 1. GET should PASS (Owner Analytics)
        $response = $this->actingAs($admin, 'sanctum')->withHeaders($headers)
            ->getJson('/api/v1/owner/analytics/master-summary');
        
        if ($response->status() !== 200) {
            dump("GET ANALYTICS FAIL:", $response->json());
        }
        $response->assertStatus(200);

        // 2. POST should BLOCK (Any write operation on a suspended tenant)
        $response2 = $this->withHeaders($headers)->postJson('/api/v1/departments', [
            'name' => 'Suspended Dept',
        ]);
        
        $response2->assertStatus(403)
                 ->assertJson(['message' => 'Account Suspended: Please renew your subscription to perform this action.']);
    }

    /**
     * Sentry Lock: Block new guest sessions if branch is suspended.
     */
    public function test_sentry_lock_guest_sessions()
    {
        $hotel = Hotel::factory()->create();
        HotelSubscription::factory()->create([
            'hotel_id' => $hotel->id,
            'status' => 'suspended',
        ]);

        $response = $this->postJson('/api/v1/guest/session/start', [
            'hotel_id' => $hotel->id,
            'context_type' => 'outlet',
            'context_id' => 1,
        ]);

        $response->assertStatus(403)
                 ->assertJson(['message' => 'Account Suspended: Please renew your subscription to perform this action.']);
    }

    /**
     * Inventory Lock: feature.guard on StockTransfer@accept.
     */
    public function test_inventory_lock_stock_transfer_accept()
    {
        $group = HotelGroup::create([
            'name' => 'Inv Group',
            'slug' => 'inv-group-' . uniqid(),
            'is_active' => true,
        ]);
        $slug = 'plan_' . uniqid();
        $plan = SubscriptionPlan::create([
            'name' => 'Pro Plan',
            'slug' => $slug,
            'features' => ['pms', 'inventory', 'analytics', 'rooms.create', 'hotel.manage'],
            'price' => 50000,
            'is_active' => true,
        ]);

        $hotel = Hotel::factory()->create([
            'hotel_group_id' => $group->id,
            'subscription_plan_id' => $plan->id
        ]);

        HotelSubscription::factory()->create([
            'hotel_id' => $hotel->id,
            'plan_id' => $plan->id,
            'status' => 'suspended',
        ]);

        $admin = User::factory()->create([
            'hotel_id' => $hotel->id,
            'hotel_group_id' => $group->id,
            'role' => 'admin',
            'is_approved' => true,
            'hardware_hash' => null,
        ]);

        $headers = [
            'X-Hardware-Id' => 'HW_STAB_INV',
            'X-Test-Verify-Subscription' => 'true',
            'X-Tenant-ID' => $hotel->id,
        ];

        $item = InventoryItem::factory()->create(['hotel_id' => $hotel->id]);
        $transfer = StockTransfer::create([
            'hotel_id' => $hotel->id,
            'inventory_item_id' => $item->id,
            'requested_by' => $admin->id,
            'quantity_requested' => 10,
            'from_location_id' => 1,
            'to_location_id' => 2,
            'status' => 'dispatched',
        ]);

        // POST /receive (Accept) should block
        $response = $this->actingAs($admin, 'sanctum')->withHeaders($headers)
            ->postJson("/api/v1/inventory/transfers/{$transfer->id}/receive", [
                'quantity' => 10,
                'pin' => '1234',
            ]);

        $response->assertStatus(403)
                 ->assertJson(['message' => 'Account Suspended: Please renew your subscription to perform this action.']);
    }

    /**
     * Scenario Z: Confirm T-3 and T-1 notifications fire correctly.
     */
    public function test_scenario_z_subscription_alerts()
    {
        Carbon::setTestNow('2026-04-20 12:00:00');

        $hotel1 = Hotel::factory()->create();
        $slug1 = 'plan_' . uniqid();
        $plan1 = SubscriptionPlan::create([
            'name' => 'Plan 1',
            'slug' => $slug1,
            'price' => 1000,
            'is_active' => true,
        ]);
        HotelSubscription::factory()->create([
            'hotel_id' => $hotel1->id,
            'plan_id' => $plan1->id,
            'status' => 'active',
            'current_period_end' => Carbon::parse('2026-04-23 12:00:00'),
        ]);

        $hotel2 = Hotel::factory()->create();
        $slug2 = 'plan_' . uniqid();
        $plan2 = SubscriptionPlan::create([
            'name' => 'Plan 2',
            'slug' => $slug2,
            'price' => 2000,
            'is_active' => true,
        ]);
        HotelSubscription::factory()->create([
            'hotel_id' => $hotel2->id,
            'plan_id' => $plan2->id,
            'status' => 'active',
            'current_period_end' => Carbon::parse('2026-04-21 12:00:00'),
        ]);

        Artisan::call('subscription:check-status');
        $output = Artisan::output();

        $this->assertStringContainsString("T-3 Warning sent for Hotel: " . $hotel1->id, $output);
        $this->assertStringContainsString("T-1 Warning sent for Hotel: " . $hotel2->id, $output);
        
        Carbon::setTestNow();
    }
}
