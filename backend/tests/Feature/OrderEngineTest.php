<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Hotel;
use App\Models\User;
use App\Models\Role;
use App\Models\Outlet;
use App\Models\Department;
use App\Models\Order;
use App\Models\Permission;
use Laravel\Sanctum\Sanctum;

class OrderEngineTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $user;
    protected $outlet;
    protected $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);

        $this->hotel = Hotel::create([
            'name' => 'Test Hotel',
            'domain' => 'test-hotel',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'POS Staff',
            'email' => 'pos@test.com',
            'password' => bcrypt('password'),
            'is_super_admin' => false,
        ]);

        $role = Role::withoutGlobalScopes()->where('slug', 'branchmanager')->first();
        if ($role) {
            $this->user->roles()->attach($role->id);
        }

        $this->outlet = Outlet::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Main Restaurant',
            'type' => 'restaurant'
        ]);

        $this->department = Department::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'name' => 'Kitchen',
            'slug' => 'kitchen',
        ]);
        
        $this->withPort(3002)->actingAs($this->user);
    }

    public function test_can_create_pos_order_with_items()
    {
        $payload = [
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->department->id,
            'order_number' => 'ORD-001',
            'order_source' => 'pos',
            'payment_method' => 'cash',
            'items' => [
                [
                    'quantity' => 2,
                    'price' => 15.50,
                    'notes' => 'No onions',
                    'kitchen_section' => 'Grill'
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/orders', $payload);

        $response->assertStatus(201)
                 ->assertJsonStructure(['message', 'order' => ['items']]);

        $this->assertDatabaseHas('orders', [
            'hotel_id' => $this->hotel->id,
            'order_number' => 'ORD-001',
            'total_amount' => 31.00,
            'status' => 'pending'
        ]);

        $order = Order::where('order_number', 'ORD-001')->first();

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'quantity' => 2,
            'price' => 15.50
        ]);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'new_status' => 'draft',
            'changed_by' => $this->user->id
        ]);
    }

    public function test_can_update_order_status_with_audit_trail()
    {
        $order = Order::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->department->id,
            'order_number' => 'ORD-002',
            'order_source' => 'qr_table',
            'status' => 'pending',
            'total_amount' => 50,
        ]);

        $response = $this->putJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'confirmed'
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'confirmed'
        ]);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'previous_status' => 'pending',
            'new_status' => 'confirmed',
            'changed_by' => $this->user->id
        ]);
    }

    public function test_cannot_update_closed_order()
    {
        $order = Order::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->department->id,
            'order_number' => 'ORD-003',
            'order_source' => 'pos',
            'status' => 'closed',
            'total_amount' => 10,
        ]);

        $response = $this->putJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'ready'
        ]);

        $response->assertStatus(400);
    }

    public function test_list_orders_is_tenant_isolated()
    {
        Order::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->department->id,
            'order_number' => 'ORD-ISOLATED',
            'order_source' => 'pos',
            'total_amount' => 10,
        ]);

        $hotel2 = Hotel::create(['name' => 'Hotel 2', 'domain' => 'hotel2']);
        Order::create([
            'hotel_id' => $hotel2->id,
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->department->id,
            'order_number' => 'ORD-OTHER',
            'order_source' => 'pos',
            'total_amount' => 10,
        ]);

        $response = $this->getJson('/api/v1/orders');
        
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonFragment(['order_number' => 'ORD-ISOLATED'])
                 ->assertJsonMissing(['order_number' => 'ORD-OTHER']);
    }
}
