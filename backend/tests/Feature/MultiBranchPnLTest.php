<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\HotelGroup;
use App\Models\Order;
use App\Models\InventoryItem;
use App\Models\StockTransfer;
use App\Models\GuestPortalSession;
use App\Models\RoomType;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MultiBranchPnLTest extends TestCase
{
    use RefreshDatabase;

    protected $group;
    protected $owner;
    protected $branchA;
    protected $branchB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = HotelGroup::create(['name' => 'Nexus Group', 'slug' => 'nexus-group']);
        $this->owner = User::factory()->create([
            'hotel_group_id' => $this->group->id,
            'hotel_id' => null,
        ]);

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
    }

    #[Test]
    public function test_scenario_u_multi_branch_pnl_export()
    {
        $ownerId = $this->owner->id;

        // 1. Seed Branch A:
        Order::factory()->create(['hotel_id' => $this->branchA->id, 'total_amount' => 1500, 'order_status' => 'served']);
        
        $itemA = InventoryItem::factory()->create(['hotel_id' => $this->branchA->id, 'cost_per_unit' => 10]);
        $outletA1 = \App\Models\Outlet::factory()->create(['hotel_id' => $this->branchA->id]);
        $outletA2 = \App\Models\Outlet::factory()->create(['hotel_id' => $this->branchA->id]);
        StockTransfer::forceCreate([
            'hotel_id' => $this->branchA->id,
            'inventory_item_id' => $itemA->id,
            'from_location_id' => $outletA1->id,
            'to_location_id'   => $outletA2->id,
            'requested_by'     => $ownerId,
            'dispatched_by'    => $ownerId,
            'received_by'      => $ownerId,
            'quantity_requested' => 5,
            'quantity_dispatched'=> 5,
            'quantity_received' => 5,
            'status' => 'completed'
        ]);
        
        $roomTypeA = \App\Models\RoomType::factory()->create(['hotel_id' => $this->branchA->id]);
        $roomA1 = Room::forceCreate(['hotel_id' => $this->branchA->id, 'room_type_id' => $roomTypeA->id, 'room_number' => '101', 'status' => 'available']);
        GuestPortalSession::forceCreate(['hotel_id' => $this->branchA->id, 'status' => 'active', 'room_id' => $roomA1->id, 'session_token' => 'token-a', 'expires_at' => now()->addHours(24)]);

        // 2. Seed Branch B:
        Order::factory()->create(['hotel_id' => $this->branchB->id, 'total_amount' => 800, 'order_status' => 'served']);
        
        $itemB = InventoryItem::factory()->create(['hotel_id' => $this->branchB->id, 'cost_per_unit' => 5]);
        $outletB1 = \App\Models\Outlet::factory()->create(['hotel_id' => $this->branchB->id]);
        $outletB2 = \App\Models\Outlet::factory()->create(['hotel_id' => $this->branchB->id]);
        StockTransfer::forceCreate([
            'hotel_id' => $this->branchB->id,
            'inventory_item_id' => $itemB->id,
            'from_location_id' => $outletB1->id,
            'to_location_id'   => $outletB2->id,
            'requested_by'     => $ownerId,
            'dispatched_by'    => $ownerId,
            'received_by'      => $ownerId,
            'quantity_requested' => 20,
            'quantity_dispatched'=> 20,
            'quantity_received' => 20,
            'status' => 'completed'
        ]);
        
        $roomTypeB = \App\Models\RoomType::factory()->create(['hotel_id' => $this->branchB->id]);
        $roomB1 = Room::forceCreate(['hotel_id' => $this->branchB->id, 'room_type_id' => $roomTypeB->id, 'room_number' => '201', 'status' => 'available']);
        $roomB2 = Room::forceCreate(['hotel_id' => $this->branchB->id, 'room_type_id' => $roomTypeB->id, 'room_number' => '202', 'status' => 'available']);
        
        GuestPortalSession::forceCreate(['hotel_id' => $this->branchB->id, 'status' => 'active', 'room_id' => $roomB1->id, 'session_token' => 'token-b1', 'expires_at' => now()->addHours(24)]);
        GuestPortalSession::forceCreate(['hotel_id' => $this->branchB->id, 'status' => 'active', 'room_id' => $roomB2->id, 'session_token' => 'token-b2', 'expires_at' => now()->addHours(24)]);

        // 3. Request Export as Owner
        $response = $this->actingAs($this->owner)
            ->get('/api/v1/owner/analytics/export');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        
        $csvContent = $response->streamedContent();

        // Branch A: Profit = 1500 - 50 = 1450
        $this->assertStringContainsString('"Branch Alpha",1500.00,50.00,1450.00,1', $csvContent);
        // Branch B: Profit = 800 - 100 = 700
        $this->assertStringContainsString('"Branch Beta",800.00,100.00,700.00,2', $csvContent);
    }
}
