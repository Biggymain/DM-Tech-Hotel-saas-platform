<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\InventoryItem;
use App\Models\LeisureBundle;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DrinkCheckTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $outlet;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $suffix = bin2hex(random_bytes(4));
        $this->hotel = Hotel::factory()->create([
            'domain' => "test-{$suffix}.com",
            'slug' => "hotel-{$suffix}"
        ]);
        $this->outlet = Outlet::factory()->create(['hotel_id' => $this->hotel->id]);
        $this->user = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $this->actingAs($this->user);
    }

    #[Test]
    public function test_mandatory_drink_deduction_on_served()
    {
        // 1. Create Menu Item (Pool Pass)
        $menuItem = MenuItem::factory()->create(['hotel_id' => $this->hotel->id]);
        
        // 2. Create Inventory Item (Bottle Water)
        $inventoryItem = InventoryItem::factory()->create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'current_stock' => 10,
        ]);

        // 3. Link them via LeisureBundle
        LeisureBundle::create([
            'menu_item_id' => $menuItem->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity' => 1
        ]);

        // 4. Create Order
        $order = Order::factory()->create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'order_status' => 'ready'
        ]);
        $order->items()->create([
            'menu_item_id' => $menuItem->id,
            'item_name' => $menuItem->name,
            'quantity' => 2,
            'price' => 50,
            'subtotal' => 100
        ]);

        // 5. Serve Order
        $orderService = app(OrderService::class);
        $orderService->updateStatus($order, 'served', 'Kitchen');

        // 6. Verify Deduction (10 - (1*2) = 8)
        $this->assertEquals(8, $inventoryItem->fresh()->current_stock);
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $inventoryItem->id,
            'quantity' => 2,
            'type' => 'out'
        ]);
    }

    #[Test]
    public function test_order_fails_to_serve_if_inventory_insufficient()
    {
        $menuItem = MenuItem::factory()->create(['hotel_id' => $this->hotel->id]);
        $inventoryItem = InventoryItem::factory()->create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'name' => 'Bottle Water',
            'current_stock' => 0
        ]);
        LeisureBundle::create([
            'menu_item_id' => $menuItem->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity' => 1
        ]);

        $order = Order::factory()->create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'order_status' => 'ready'
        ]);
        $order->items()->create([
            'menu_item_id' => $menuItem->id,
            'quantity' => 1,
            'price' => 50,
            'subtotal' => 50
        ]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage("Cannot serve Pool Pass: Drink inventory (Bottle Water) is empty.");

        $orderService = app(OrderService::class);
        $orderService->updateStatus($order, 'served', 'Kitchen');
    }
}
