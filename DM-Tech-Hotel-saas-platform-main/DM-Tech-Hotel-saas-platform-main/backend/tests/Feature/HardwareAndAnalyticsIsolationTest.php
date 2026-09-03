<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\HotelGroup;
use App\Models\Order;
use App\Models\InventoryItem;
use App\Models\GuestPortalSession;
use App\Models\User;
use App\Events\OrderCreatedBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class HardwareAndAnalyticsIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected $group;
    protected $owner;
    protected $branchA;
    protected $branchB;
    protected $staffA;
    protected $staffB;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Organization
        $this->group = HotelGroup::create(['name' => 'Elite Hotels Group', 'slug' => 'elite-hotels']);
        $this->owner = User::factory()->create([
            'hotel_group_id' => $this->group->id,
            'hotel_id' => null, // Owner context
        ]);

        // 2. Setup Branches
        $this->branchA = Hotel::factory()->create([
            'hotel_group_id' => $this->group->id,
            'name' => 'Branch Alpha',
            'slug' => 'branch-alpha'
        ]);
        $this->branchB = Hotel::factory()->create([
            'hotel_group_id' => $this->group->id,
            'name' => 'Branch Beta',
            'slug' => 'branch-beta'
        ]);

        // 3. Setup Staff
        $this->staffA = User::factory()->create(['hotel_id' => $this->branchA->id]);
        $this->staffB = User::factory()->create(['hotel_id' => $this->branchB->id]);
    }

    /**
     * Scenario S: Verify Branch Isolation
     */
    #[Test]
    public function test_scenario_s_branch_isolation_enforcement()
    {
        $this->withoutMiddleware([\App\Http\Middleware\EnsureActiveSubscription::class, \App\Http\Middleware\RoleVerificationMiddleware::class]);

        // 1. Data Creation in Branch A
        Order::factory()->create(['hotel_id' => $this->branchA->id, 'total_amount' => 100]);
        
        // 2. Access Branch A data as Staff A (Should succeed)
        $responseA = $this->actingAs($this->staffA)
            ->withHeader('X-Tenant-ID', $this->branchA->id)
            ->getJson('/api/v1/orders');
        $responseA->assertStatus(200);
        $this->assertCount(1, $responseA->json('data'));

        // 3. Attempt to access Branch A data as Staff B
        // TenantMiddleware will detect the mismatch between user->hotel_id and X-Tenant-ID and return 403.
        $responseB = $this->actingAs($this->staffB)
            ->withHeader('X-Tenant-ID', $this->branchA->id) 
            ->getJson('/api/v1/orders');
        
        $responseB->assertStatus(403);
    }

    /**
     * Scenario S: Real-time isolation
     */
    #[Test]
    public function test_scenario_s_real_time_event_routing()
    {
        $event = new OrderCreatedBroadcast($this->branchA->id, 1, ['id' => 1]);
        $channels = $event->broadcastOn();

        // Verify channel name includes branch context
        $channelNames = array_map(fn($c) => $c->name, $channels);
        $this->assertContains("private-hotel.{$this->branchA->id}.branch.1.orders", $channelNames);
        $this->assertNotContains("private-hotel.{$this->branchB->id}.branch.1.orders", $channelNames);
    }

    /**
     * Scenario T: Owner Analytics Aggregation
     */
    #[Test]
    public function test_scenario_t_owner_master_analytics_summation()
    {
        // 1. Seed Branch A
        Order::factory()->create(['hotel_id' => $this->branchA->id, 'total_amount' => 250, 'order_status' => 'served']);
        InventoryItem::factory()->create([
            'hotel_id' => $this->branchA->id,
            'current_stock' => 10,
            'cost_per_unit' => 5
        ]); // Value: 50

        // 2. Seed Branch B
        Order::factory()->create(['hotel_id' => $this->branchB->id, 'total_amount' => 750, 'order_status' => 'served']);
        InventoryItem::factory()->create([
            'hotel_id' => $this->branchB->id,
            'current_stock' => 20,
            'cost_per_unit' => 10
        ]); // Value: 200

        // 3. Seed Sessions (Include required expires_at and dependent Room FKs)
        $roomTypeA = \App\Models\RoomType::factory()->create(['hotel_id' => $this->branchA->id]);
        $roomTypeB = \App\Models\RoomType::factory()->create(['hotel_id' => $this->branchB->id]);

        $room1 = \App\Models\Room::forceCreate(['hotel_id' => $this->branchA->id, 'room_type_id' => $roomTypeA->id, 'room_number' => '101', 'status' => 'available']);
        $room2 = \App\Models\Room::forceCreate(['hotel_id' => $this->branchB->id, 'room_type_id' => $roomTypeB->id, 'room_number' => '102', 'status' => 'available']);
        $room3 = \App\Models\Room::forceCreate(['hotel_id' => $this->branchB->id, 'room_type_id' => $roomTypeB->id, 'room_number' => '103', 'status' => 'available']);

        GuestPortalSession::forceCreate(['hotel_id' => $this->branchA->id, 'status' => 'active', 'room_id' => $room1->id, 'session_token' => 't1', 'expires_at' => now()->addHours(24)]);
        GuestPortalSession::forceCreate(['hotel_id' => $this->branchB->id, 'status' => 'active', 'room_id' => $room2->id, 'session_token' => 't2', 'expires_at' => now()->addHours(24)]);
        GuestPortalSession::forceCreate(['hotel_id' => $this->branchB->id, 'status' => 'revoked', 'room_id' => $room3->id, 'session_token' => 't3', 'expires_at' => now()->addHours(24)]);

        // 4. Request Master Summary as Owner
        $response = $this->actingAs($this->owner)
            ->getJson('/api/v1/owner/analytics/master-summary');

        $response->assertStatus(200);
        $metrics = $response->json('data.metrics');

        // Revenue: 250 + 750 = 1000
        $this->assertEquals(1000, $metrics['total_revenue']);
        
        // Stock Value: 50 + 200 = 250
        $this->assertEquals(250, $metrics['total_stock_value']);

        // Active Sessions: 1 (A) + 1 (B) = 2
        $this->assertEquals(2, $metrics['total_active_sessions']);
        
        $this->assertEquals(2, $response->json('data.branch_count'));
    }

    #[Test]
    public function test_hardware_device_registry_isolation()
    {
        // 1. Create Hardware in Branch A
        \App\Models\HardwareDevice::create([
            'hotel_id' => $this->branchA->id,
            'branch_id' => 101,
            'device_name' => 'POS-FRONT-A',
            'hardware_uuid' => 'UUID-A-123',
            'zone_type' => 'restricted',
            'is_verified' => false
        ]);

        // 2. Verify Staff A can see it
        // We act as Staff A and hit an endpoint that uses HardwareDevice or just check count in this context
        // To truly test TenantScope, we should use actingAs.
        // But since we are directly calling the Model, we need to ensure TenantScope is applied.
        
        $this->actingAs($this->staffA);
        $this->assertEquals(1, \App\Models\HardwareDevice::count());

        // 3. Verify Staff B cannot see it
        $this->actingAs($this->staffB);
        $this->assertEquals(0, \App\Models\HardwareDevice::count());
    }
}
