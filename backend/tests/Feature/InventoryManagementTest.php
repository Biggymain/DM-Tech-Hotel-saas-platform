<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Hotel;
use App\Models\Outlet;
use App\Models\Department;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\MenuItemIngredient;
use App\Models\Order;
use App\Models\Role;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $hotel;
    protected $outlet;
    protected $department;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->withoutMiddleware();
        
        $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
        
        $this->hotel = Hotel::create(['name' => 'Royal Spring Hotel']);
        
        $this->user = User::factory()->create([
            'hotel_id' => $this->hotel->id
        ]);
        
        // Attach Role correctly
        $role = Role::where('name', 'Manager')->first();
        if ($role) {
            $this->user->roles()->attach($role->id);
        }

        $this->department = Department::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Kitchen',
            'slug' => 'kitchen'
        ]);

        $this->outlet = Outlet::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Restaurant Grill',
            'type' => 'restaurant'
        ]);
    }

    public function test_can_view_inventory_items_scoped_to_hotel()
    {
        $otherHotel = Hotel::create(['name' => 'Other Hotel']);
        
        InventoryItem::create([
            'hotel_id' => $this->hotel->id,
            'sku' => 'BEEF-01',
            'name' => 'Wagyu Beef',
            'unit_of_measurement' => 'kg',
            'current_stock' => 10,
            'minimum_stock_level' => 2
        ]);
        
        InventoryItem::create([
            'hotel_id' => $otherHotel->id,
            'sku' => 'CHIC-01',
            'name' => 'Chicken Breast',
            'unit_of_measurement' => 'kg',
            'current_stock' => 50,
            'minimum_stock_level' => 10
        ]);
        
        $response = $this->actingAs($this->user)->getJson('/api/v1/inventory/items');
        
        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $this->assertEquals('BEEF-01', $response->json(0)['sku']);
    }

    public function test_stock_reservation_and_deduction_workflow()
    {
        $beef = InventoryItem::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'sku' => 'BEEF-01',
            'name' => 'Wagyu Beef',
            'unit_of_measurement' => 'kg',
            'current_stock' => 10,
            'minimum_stock_level' => 2
        ]);

        $salt = InventoryItem::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'sku' => 'SALT-01',
            'name' => 'Sea Salt',
            'unit_of_measurement' => 'g',
            'current_stock' => 1000,
            'minimum_stock_level' => 100
        ]);

        $steakMenu = MenuItem::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'name' => 'Wagyu Steak',
            'price' => 150,
        ]);
        
        $steakMenu->ingredients()->create([
            'inventory_item_id' => $beef->id,
            'quantity_required' => 0.4
        ]);
        
        $steakMenu->ingredients()->create([
            'inventory_item_id' => $salt->id,
            'quantity_required' => 10
        ]);

        $order = Order::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->department->id,
            'order_number' => 'ORD-12345',
            'order_source' => 'pos',
            'status' => 'pending',
            'total_amount' => 150,
            'payment_status' => 'unpaid',
            'created_by' => $this->user->id,
        ]);

        $order->items()->create([
            'menu_item_id' => $steakMenu->id,
            'quantity' => 2,
            'price' => 150,
        ]);

        // Phase 1: Reservation
        $response = $this->actingAs($this->user)->putJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'confirmed'
        ]);
        
        $response->assertStatus(200);
        
        $beef->refresh();
        $salt->refresh();

        $this->assertEquals(10, $beef->current_stock);
        $this->assertEquals(0.8, $beef->reserved_stock);
        
        $this->assertEquals(1000, $salt->current_stock);
        $this->assertEquals(20, $salt->reserved_stock);

        // Phase 2: Actual Deduction
        $response = $this->actingAs($this->user)->putJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'served'
        ]);
        
        $response->assertStatus(200);
        
        $beef->refresh();
        $salt->refresh();
        
        $this->assertEquals(9.2, $beef->current_stock);
        $this->assertEquals(0, $beef->reserved_stock);
        
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $beef->id,
            'type' => 'out',
            'quantity' => 0.8,
            'reference_type' => 'order',
            'reference_id' => $order->id
        ]);
    }
    
    public function test_reservation_throws_insufficient_stock_exception()
    {
        $beef = InventoryItem::create([
            'hotel_id' => $this->hotel->id,
            'sku' => 'BEEF-02',
            'name' => 'Wagyu Beef',
            'unit_of_measurement' => 'kg',
            'current_stock' => 0.5,
            'minimum_stock_level' => 0
        ]);

        $steakMenu = MenuItem::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'name' => 'Wagyu Steak',
            'price' => 150,
        ]);
        
        $steakMenu->ingredients()->create([
            'inventory_item_id' => $beef->id,
            'quantity_required' => 0.4
        ]);

        $order = Order::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->department->id,
            'order_number' => 'ORD-999',
            'order_source' => 'pos',
            'status' => 'pending',
            'total_amount' => 300,
            'created_by' => $this->user->id,
        ]);

        $order->items()->create([
            'menu_item_id' => $steakMenu->id,
            'quantity' => 2,
            'price' => 150,
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'confirmed'
        ]);
        
        $response->assertStatus(422);
        
        $beef->refresh();
        $this->assertEquals(0, $beef->reserved_stock); // Must not update if exception thrown
    }
}
