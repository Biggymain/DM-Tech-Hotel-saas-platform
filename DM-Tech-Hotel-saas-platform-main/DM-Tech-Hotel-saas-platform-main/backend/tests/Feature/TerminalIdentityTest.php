<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Order;
use App\Models\User;
use App\Models\Outlet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TerminalIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $outlet;
    protected $waitress;
    protected $terminalUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hotel = Hotel::factory()->create();
        $this->outlet = Outlet::factory()->create(['hotel_id' => $this->hotel->id]);
        $this->waitress = User::factory()->create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'is_on_duty' => true,
            'role' => 'waitress'
        ]);
        $this->terminalUser = User::factory()->create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
        ]);
        
        $terminalRole = \App\Models\Role::create(['name' => 'Terminal', 'slug' => 'terminal', 'hotel_id' => $this->hotel->id]);
        $viewPerm = \App\Models\Permission::firstOrCreate(['slug' => 'orders.view', 'name' => 'View Orders', 'module' => 'orders']);
        $posPerm = \App\Models\Permission::firstOrCreate(['slug' => 'pos.manage', 'name' => 'Manage POS', 'module' => 'pos']);
        $terminalRole->permissions()->attach([$viewPerm->id, $posPerm->id]);
        $this->terminalUser->roles()->attach($terminalRole->id, ['hotel_id' => $this->hotel->id]);

        $waitressRole = \App\Models\Role::create(['name' => 'Waitress', 'slug' => 'waitress', 'hotel_id' => $this->hotel->id]);
        $this->waitress->roles()->attach($waitressRole->id, ['hotel_id' => $this->hotel->id]);
    }
    #[Test]
    public function test_order_attribution_to_waitress_id()
    {
        $dept = \App\Models\Department::factory()->create(['hotel_id' => $this->hotel->id]);
        $menuItem = \App\Models\MenuItem::factory()->create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->hotel->departments()->first()?->id ?? $dept->id
        ]);

        $payload = [
            'outlet_id' => $this->outlet->id,
            'department_id' => $dept->id,
            'waiter_id' => $this->waitress->id,
            'items' => [
                [
                    'menu_item_id' => $menuItem->id,
                    'quantity' => 2,
                    'unit_price' => 1500
                ]
            ]
        ];

        $response = $this->actingAs($this->terminalUser)
            ->postJson('/api/v1/orders', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('orders', [
            'waiter_id' => $this->waitress->id,
            'created_by' => $this->terminalUser->id
        ]);
    }

    #[Test]
    public function test_cannot_attribute_to_off_duty_staff()
    {
        $this->waitress->update(['is_on_duty' => false]);

        $dept = \App\Models\Department::factory()->create(['hotel_id' => $this->hotel->id]);
        $menuItem = \App\Models\MenuItem::factory()->create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'department_id' => $dept->id
        ]);

        $payload = [
            'outlet_id' => $this->outlet->id,
            'department_id' => $dept->id,
            'waiter_id' => $this->waitress->id,
            'items' => [
                [
                    'menu_item_id' => $menuItem->id,
                    'quantity' => 1,
                    'unit_price' => 1000
                ]
            ]
        ];

        $response = $this->actingAs($this->terminalUser)
            ->postJson('/api/v1/orders', $payload);

        $response->assertStatus(403);
        $response->assertJsonFragment(['message' => 'Staff member is not on duty.']);
    }

    #[Test]
    public function test_filtering_orders_by_waiter_id()
    {
        $dept = \App\Models\Department::factory()->create(['hotel_id' => $this->hotel->id]);
        $otherWaiter = User::factory()->create(['hotel_id' => $this->hotel->id]);

        // Order for waitress
        Order::factory()->create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'waiter_id' => $this->waitress->id,
            'department_id' => $dept->id,
            'order_number' => 'W-001'
        ]);

        // Order for someone else
        Order::factory()->create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'waiter_id' => $otherWaiter->id,
            'department_id' => $dept->id,
            'order_number' => 'O-001'
        ]);

        $response = $this->actingAs($this->terminalUser)
            ->getJson("/api/v1/orders?waiter_id={$this->waitress->id}");

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals('W-001', $response->json('data.0.order_number'));
    }
}
