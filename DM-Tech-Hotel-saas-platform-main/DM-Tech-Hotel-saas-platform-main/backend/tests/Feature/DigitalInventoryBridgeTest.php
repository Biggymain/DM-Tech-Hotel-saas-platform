<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\InventoryItem;
use App\Models\Outlet;
use App\Models\StaffDailyPin;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DigitalInventoryBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $mainStore;
    protected $loungeOutlet;
    protected $staff;
    protected $item;
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hotel = Hotel::factory()->create();
        $this->mainStore = Outlet::factory()->create(['hotel_id' => $this->hotel->id, 'name' => 'Main Store']);
        $this->loungeOutlet = Outlet::factory()->create(['hotel_id' => $this->hotel->id, 'name' => 'Lounge']);
        // Roles and Perms
        $managerRole = \App\Models\Role::create(['hotel_id' => $this->hotel->id, 'name' => 'Outlet Manager', 'slug' => 'outletmanager']);
        $perm = \App\Models\Permission::create(['name' => 'inventory.manage', 'slug' => 'inventory.manage']);
        $managerRole->permissions()->attach($perm->id, ['hotel_id' => $this->hotel->id]);

        $this->staff = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $this->staff->roles()->attach($managerRole->id, ['hotel_id' => $this->hotel->id]);
        
        $this->item = InventoryItem::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->mainStore->id,
            'name' => 'Whiskey',
            'sku' => 'WHI-SKU',
            'current_stock' => 100,
            'status' => 'active'
        ]);

        $this->service = app(StockTransferService::class);
    }

    #[Test]
    public function scenario_p_honest_handshake_updates_counts()
    {
        // Setup PIN
        StaffDailyPin::create([
            'user_id' => $this->staff->id,
            'hotel_id' => $this->hotel->id,
            'pin_hash' => Hash::make('1234'),
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($this->staff);

        // 1. Initiate
        $transfer = $this->service->initiateTransfer($this->item->id, 10, $this->mainStore->id, $this->loungeOutlet->id, $this->staff->id);
        $this->assertEquals('pending', $transfer->status);

        // 2. Dispatch
        $this->service->dispatchTransfer($transfer->id, $this->staff->id, 10);
        $this->item->refresh();
        $this->assertEquals(90, $this->item->current_stock);

        // 3. Accept (Honest Handshake)
        $this->service->acceptTransfer($transfer->id, $this->staff->id, '1234', 10);
        
        $transfer->refresh();
        $this->assertEquals('completed', $transfer->status);

        $loungeItem = InventoryItem::where('sku', 'WHI-SKU')->where('outlet_id', $this->loungeOutlet->id)->first();
        $this->assertEquals(10, $loungeItem->current_stock);
    }

    #[Test]
    public function scenario_q_pin_failure_denies_transfer()
    {
        // Setup wrong PIN
        StaffDailyPin::create([
            'user_id' => $this->staff->id,
            'hotel_id' => $this->hotel->id,
            'pin_hash' => Hash::make('1234'),
            'expires_at' => now()->addHour(),
        ]);

        $transfer = StockTransfer::create([
            'hotel_id' => $this->hotel->id,
            'inventory_item_id' => $this->item->id,
            'from_location_id' => $this->mainStore->id,
            'to_location_id' => $this->loungeOutlet->id,
            'quantity_requested' => 10,
            'quantity_dispatched' => 10,
            'status' => 'in_transit',
            'requested_by' => $this->staff->id,
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Handshake failed');

        $this->service->acceptTransfer($transfer->id, $this->staff->id, 'wrong', 10);
    }

    #[Test]
    public function scenario_r_shortage_triggers_dispute_and_high_severity_log()
    {
        // Setup PIN
        StaffDailyPin::create([
            'user_id' => $this->staff->id,
            'hotel_id' => $this->hotel->id,
            'pin_hash' => Hash::make('1234'),
            'expires_at' => now()->addHour(),
        ]);

        $transfer = StockTransfer::create([
            'hotel_id' => $this->hotel->id,
            'inventory_item_id' => $this->item->id,
            'from_location_id' => $this->mainStore->id,
            'to_location_id' => $this->loungeOutlet->id,
            'quantity_requested' => 10,
            'quantity_dispatched' => 10,
            'status' => 'in_transit',
            'requested_by' => $this->staff->id,
        ]);

        // Accept with shortage (only 8 received)
        $this->service->acceptTransfer($transfer->id, $this->staff->id, '1234', 8);

        $transfer->refresh();
        $this->assertEquals('disputed', $transfer->status);

        // Verify high-severity AuditLog
        $log = AuditLog::where('change_type', 'stock_transfer_dispute')
            ->where('entity_id', $transfer->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertGreaterThanOrEqual(10, $log->severity_score);
    }

    #[Test]
    public function test_api_dispatch_flow_v1()
    {
        $this->actingAs($this->staff);
        
        $transfer = StockTransfer::create([
            'hotel_id' => $this->hotel->id,
            'inventory_item_id' => $this->item->id,
            'from_location_id' => $this->mainStore->id,
            'to_location_id' => $this->loungeOutlet->id,
            'quantity_requested' => 10,
            'requested_by' => $this->staff->id,
            'status' => 'pending',
        ]);

        $this->postJson("/api/v1/inventory/transfers/{$transfer->id}/dispatch", [
            'quantity' => 10
        ])->assertStatus(200);

        $transfer->refresh();
        $this->assertEquals('in_transit', $transfer->status);
    }

    #[Test]
    public function test_api_receive_dispute_flow_v1()
    {
        StaffDailyPin::create([
            'user_id' => $this->staff->id,
            'hotel_id' => $this->hotel->id,
            'pin_hash' => Hash::make('1234'),
            'expires_at' => now()->addHour(),
        ]);

        $this->actingAs($this->staff);
        
        $transfer = StockTransfer::create([
            'hotel_id' => $this->hotel->id,
            'inventory_item_id' => $this->item->id,
            'from_location_id' => $this->mainStore->id,
            'to_location_id' => $this->loungeOutlet->id,
            'quantity_requested' => 10,
            'quantity_dispatched' => 10,
            'status' => 'in_transit',
            'requested_by' => $this->staff->id,
        ]);

        $this->postJson("/api/v1/inventory/transfers/{$transfer->id}/receive", [
            'quantity' => 5, // Big shortage
            'pin' => '1234'
        ])->assertStatus(200);

        $transfer->refresh();
        $this->assertEquals('disputed', $transfer->status);
    }
}
