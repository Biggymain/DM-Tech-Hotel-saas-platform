<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\InventoryItem;
use App\Models\Outlet;
use App\Models\StaffDailyPin;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class InventoryHandshakeTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $mainStore;
    protected $barOutlet;
    protected $storekeeper;
    protected $barman;
    protected $sourceItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::factory()->create();
        
        // Roles and Perms
        $managerRole = Role::create(['hotel_id' => $this->hotel->id, 'name' => 'Outlet Manager', 'slug' => 'outletmanager']);
        $perm = Permission::create(['name' => 'inventory.manage', 'slug' => 'inventory.manage']);
        $managerRole->permissions()->attach($perm->id, ['hotel_id' => $this->hotel->id]);

        $this->storekeeper = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $this->barman = User::factory()->create(['hotel_id' => $this->hotel->id]);
        
        $this->storekeeper->roles()->attach($managerRole->id, ['hotel_id' => $this->hotel->id]);
        $this->barman->roles()->attach($managerRole->id, ['hotel_id' => $this->hotel->id]);

        $this->mainStore = Outlet::factory()->create(['hotel_id' => $this->hotel->id, 'name' => 'Main Store']);
        $this->barOutlet = Outlet::factory()->create(['hotel_id' => $this->hotel->id, 'name' => 'The Bar']);

        $this->sourceItem = InventoryItem::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->mainStore->id,
            'name' => 'Beer',
            'sku' => 'BEER-001',
            'current_stock' => 100,
            'status' => 'active'
        ]);
    }

    #[Test]
    public function test_dispatch_deducts_from_source_immediately()
    {
        $this->storekeeper->refresh();
        $this->actingAs($this->storekeeper);

        $transfer = StockTransfer::create([
            'hotel_id' => $this->hotel->id,
            'inventory_item_id' => $this->sourceItem->id,
            'from_location_id' => $this->mainStore->id,
            'to_location_id' => $this->barOutlet->id,
            'quantity_requested' => 10,
            'requested_by' => $this->barman->id,
            'status' => 'pending',
        ]);

        $this->postJson("/api/v1/inventory/transfers/{$transfer->id}/dispatch", [
            'quantity' => 10
        ])->assertStatus(200);

        $this->sourceItem->refresh();
        $this->assertEquals(90, $this->sourceItem->current_stock);
        
        $transfer->refresh();
        $this->assertEquals('in_transit', $transfer->status);
    }

    #[Test]
    public function test_receive_fails_without_valid_daily_pin()
    {
        $this->actingAs($this->storekeeper);

        $transfer = StockTransfer::create([
            'hotel_id' => $this->hotel->id,
            'inventory_item_id' => $this->sourceItem->id,
            'from_location_id' => $this->mainStore->id,
            'to_location_id' => $this->barOutlet->id,
            'quantity_requested' => 10,
            'quantity_dispatched' => 10,
            'requested_by' => $this->barman->id,
            'status' => 'in_transit',
        ]);

        $this->barman->refresh();
        $this->actingAs($this->barman);

        // No PIN set yet
        $this->postJson("/api/v1/inventory/transfers/{$transfer->id}/receive", [
            'quantity' => 10,
            'pin' => '1234'
        ])->assertStatus(403);

        // Set PIN but provide wrong one
        $this->postJson('/api/v1/auth/staff/set-pin', ['pin' => '1234'])->assertStatus(200);
        
        $this->postJson("/api/v1/inventory/transfers/{$transfer->id}/receive", [
            'quantity' => 10,
            'pin' => '0000'
        ])->assertStatus(403);
    }

    #[Test]
    public function test_receive_handshake_shifts_liability_to_destination()
    {
        $this->barman->refresh();
        $this->actingAs($this->barman);
        $this->postJson('/api/v1/auth/staff/set-pin', ['pin' => '1234'])->assertStatus(200);

        $transfer = StockTransfer::create([
            'hotel_id' => $this->hotel->id,
            'inventory_item_id' => $this->sourceItem->id,
            'from_location_id' => $this->mainStore->id,
            'to_location_id' => $this->barOutlet->id,
            'quantity_requested' => 10,
            'quantity_dispatched' => 10,
            'requested_by' => $this->barman->id,
            'status' => 'in_transit',
        ]);

        $this->postJson("/api/v1/inventory/transfers/{$transfer->id}/receive", [
            'quantity' => 10,
            'pin' => '1234'
        ])->assertStatus(200);

        $transfer->refresh();
        $this->assertEquals('completed', $transfer->status);

        // Check destination stock
        $destItem = InventoryItem::where('sku', 'BEER-001')
            ->where('outlet_id', $this->barOutlet->id)
            ->first();

        $this->assertNotNull($destItem);
        $this->assertEquals(10, $destItem->current_stock);
    }
}
