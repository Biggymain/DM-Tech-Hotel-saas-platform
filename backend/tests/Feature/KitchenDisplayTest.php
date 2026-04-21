<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Hotel;
use App\Models\User;
use App\Models\Role;
use App\Models\Outlet;
use App\Models\Department;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;

class KitchenDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $user;
    protected $outlet;
    protected $department;
    protected $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::create([
            'name' => 'KDS Test Hotel',
            'domain' => 'kds-test',
            'is_active' => true,
        ]);

        $role = Role::create([
            'name' => 'Kitchen Staff', 
            'slug' => 'kitchenstaff', 
            'hotel_id' => $this->hotel->id
        ]);
        
        $permissions = [
            'kds.view',
            'kds.update',
            'kds.manage',
            'orders.create',
            'pos.manage',
        ];

        foreach ($permissions as $permName) {
            $perm = \App\Models\Permission::create([
                'name' => ucfirst(str_replace('.', ' ', $permName)),
                'slug' => $permName,
                'module' => 'KDS',
                'hotel_id' => $this->hotel->id
            ]);
            $role->permissions()->attach($perm->id, ['hotel_id' => $this->hotel->id]);
        }
        
        $this->outlet = Outlet::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Main Restaurant',
            'type' => 'restaurant'
        ]);

        $this->user = User::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'name' => 'Chef',
            'email' => 'chef@kds.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'is_super_admin' => false,
            'is_approved' => true,
            'is_on_duty' => true,
            'hardware_hash' => \Tests\TestCase::generateMockHardwareHash(),
        ]);

        $this->user->roles()->attach($role->id, ['hotel_id' => $this->hotel->id]);
        
        app(\App\Services\PermissionService::class)->clearPermissionCache($this->user);
        
        $this->user = User::with('roles.permissions')->find($this->user->id);
        Sanctum::actingAs($this->user, ['*']);

        $this->department = Department::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'name' => 'Kitchen',
            'slug' => 'kitchen'
        ]);

        // User is already linked to outlet_id, which provides the necessary context for KDS access.

        $category = MenuCategory::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Mains'
        ]);

        $this->menuItem = MenuItem::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'menu_category_id' => $category->id,
            'department_id' => $this->department->id,
            'name' => 'Burger',
            'price' => 15.00,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function test_kitchen_ticket_automatically_created_when_order_placed()
    {
        $payload = [
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->department->id,
            'order_number' => 'ORD-1234',
            'order_source' => 'pos',
            'items' => [
                [
                    'menu_item_id' => $this->menuItem->id,
                    'quantity' => 2,
                    'price' => 15.00,
                    'kitchen_section' => 'Grill'
                ]
            ]
        ];

        // This triggers OrderController@store which generates an Order -> triggers OrderCreated event -> generates KitchenTicket
        $response = $this->postJson('/api/v1/orders', $payload);
        $response->assertStatus(201);
        
        $orderId = $response->json('order.id');

        // Check if KDS Ticket exists
        $this->assertDatabaseHas('kitchen_tickets', [
            'hotel_id' => $this->hotel->id,
            'order_id' => $orderId,
            'department_id' => $this->department->id,
            'status' => 'queued',
        ]);

        // Check KDS Ticket Item exists mapped properly
        $ticket = KitchenTicket::where('order_id', $orderId)->first();
        $this->assertNotNull($ticket);

        $this->assertDatabaseHas('kitchen_ticket_items', [
            'kitchen_ticket_id' => $ticket->id,
            'menu_item_id' => $this->menuItem->id,
            'kitchen_section' => 'Grill',
            'quantity' => 2,
        ]);
    }

    #[Test]
    public function test_kds_status_transitions_and_history_logging()
    {
        // Setup initial order & ticket implicitly
        $order = Order::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->department->id,
            'order_number' => 'ORD-9999',
            'order_source' => 'pos', // This will fire Eloquent `created` event inherently directly creating the KDS items
            'status' => 'pending',
            'total_amount' => 10,
        ]);

        // Mock OrderItem mapped manually to trigger the listener effectively ... wait, listener on order creation expects items to exist, but Order::create fires before items are attached! 
        // Thus listener doesn't see items if done this way. Let's rely on API.
        
        $payload = [
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->department->id,
            'order_number' => 'ORD-5555',
            'order_source' => 'pos',
            'items' => [
                [
                    'menu_item_id' => $this->menuItem->id,
                    'quantity' => 1,
                    'price' => 15.00,
                ]
            ]
        ];

        $this->postJson('/api/v1/orders', $payload);
        
        $ticket = KitchenTicket::where('ticket_number', 'like', '%ORD-5555%')->first();
        $this->assertNotNull($ticket);

        // API Call to Update Ticket Status
        $updateResponse = $this->putJson("/api/v1/kds/tickets/{$ticket->id}/status", [
            'status' => 'preparing'
        ]);

        $updateResponse->assertStatus(200);

        $this->assertDatabaseHas('kitchen_tickets', [
            'id' => $ticket->id,
            'status' => 'preparing'
        ]);

        // Check History Log
        $this->assertDatabaseHas('kitchen_ticket_status_histories', [
            'kitchen_ticket_id' => $ticket->id,
            'previous_status' => 'queued',
            'new_status' => 'preparing',
            'changed_by_user_id' => $this->user->id
        ]);
        
        // API Call to Update Item Status
        $item = $ticket->items()->first();
        $itemResponse = $this->putJson("/api/v1/kds/items/{$item->id}/status", [
            'status' => 'ready'
        ]);
        
        $itemResponse->assertStatus(200);
        $this->assertDatabaseHas('kitchen_ticket_items', [
            'id' => $item->id,
            'status' => 'ready'
        ]);
    }

    #[Test]
    public function test_tenant_isolation_prevents_unauthorized_kds_access()
    {
        $hotel2 = Hotel::create(['name' => 'H2', 'domain' => 'h2', 'is_active' => true]);
        
        $outlet2 = Outlet::create(['hotel_id' => $hotel2->id, 'name' => 'O', 'type' => 'restaurant']);
        $dept2 = Department::create(['hotel_id' => $hotel2->id, 'outlet_id' => $outlet2->id, 'name' => 'D', 'slug' => 'd']);
        $order2 = Order::create(['hotel_id' => $hotel2->id, 'outlet_id' => $outlet2->id, 'department_id' => $dept2->id, 'order_number' => 'H2', 'order_source' => 'pos', 'status' => 'pending', 'total_amount' => 1]);

        $ticketH2 = KitchenTicket::create([
            'hotel_id' => $hotel2->id,
            'order_id' => $order2->id, 
            'department_id' => $dept2->id, 
            'outlet_id' => $outlet2->id, 
            'ticket_number' => 'H2-TICKET',
            'status' => 'queued'
        ]);

        $response = $this->getJson('/api/v1/kds/tickets');
        $response->assertStatus(200)
                 ->assertJsonCount(0); // Should not see H2 ticket
                 
        $failResponse = $this->getJson("/api/v1/kds/tickets/{$ticketH2->id}");
        $failResponse->assertStatus(404);
    }
}
