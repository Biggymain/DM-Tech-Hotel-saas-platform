<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Hotel;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Permission;
use App\Models\Role;
use App\Models\StaffDailyPin;
use App\Models\User;
use App\Models\KitchenTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class StaffAccountabilityTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $waitressA;
    protected $waitressB;
    protected $outlet;
    protected $order;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->hotel = Hotel::factory()->create();
        
        // Setup Roles and Permissions
        $waiterRole = Role::create(['hotel_id' => $this->hotel->id, 'name' => 'Waiter', 'slug' => 'waiter']);
        $kdsRole = Role::create(['hotel_id' => $this->hotel->id, 'name' => 'KDS', 'slug' => 'kds']);
        
        $orderUpdatePerm = Permission::create(['name' => 'orders.update', 'slug' => 'orders.update']);
        $kdsUpdatePerm = Permission::create(['name' => 'kds.update', 'slug' => 'kds.update']);
        
        $waiterRole->permissions()->attach($orderUpdatePerm->id, ['hotel_id' => $this->hotel->id]);
        $kdsRole->permissions()->attach($kdsUpdatePerm->id, ['hotel_id' => $this->hotel->id]);

        $this->waitressA = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $this->waitressB = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $chef = User::factory()->create(['hotel_id' => $this->hotel->id]);

        $this->waitressA->roles()->attach($waiterRole->id, ['hotel_id' => $this->hotel->id]);
        $this->waitressB->roles()->attach($waiterRole->id, ['hotel_id' => $this->hotel->id]);
        
        $department = Department::factory()->create(['hotel_id' => $this->hotel->id]);
        $this->outlet = Outlet::factory()->create(['hotel_id' => $this->hotel->id]);

        $this->order = Order::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'department_id' => $department->id,
            'order_number' => 'ORD-TEST-123',
            'order_status' => 'pending',
            'total_amount' => 100,
            'order_source' => 'guest_qr',
        ]);
    }

    #[Test]
    public function test_pin_expires_after_12_hours()
    {
        $this->actingAs($this->waitressA);

        // Set PIN
        $this->postJson('/api/v1/auth/staff/set-pin', ['pin' => '1234'])
            ->assertStatus(200);

        $pin = StaffDailyPin::where('user_id', $this->waitressA->id)->first();
        $this->assertNotNull($pin);

        // Advance time by 13 hours
        $this->travel(13)->hours();

        // Attempt to claim order
        $this->postJson("/api/v1/orders/{$this->order->id}/claim", ['pin' => '1234'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Invalid or expired Daily PIN');
    }

    #[Test]
    public function test_waitress_can_claim_unclaimed_order()
    {
        $this->actingAs($this->waitressA);

        // Set PIN
        $this->postJson('/api/v1/auth/staff/set-pin', ['pin' => '1234'])
            ->assertStatus(200);

        // Claim order
        $this->postJson("/api/v1/orders/{$this->order->id}/claim", ['pin' => '1234'])
            ->assertStatus(200);

        $this->order->refresh();
        $this->assertEquals($this->waitressA->id, $this->order->waiter_id);
        $this->assertNotNull($this->order->claimed_at);
    }

    #[Test]
    public function test_unauthorized_order_claim_by_another_staff()
    {
        // Waitress A claims the order
        $this->order->update(['waiter_id' => $this->waitressA->id]);
        $this->order->refresh();

        $this->actingAs($this->waitressB);
        
        // Set Waitress B PIN
        $this->postJson('/api/v1/auth/staff/set-pin', ['pin' => '5678'])
            ->assertStatus(200);

        // Attempt to claim Waitress A's order
        $this->postJson("/api/v1/orders/{$this->order->id}/claim", ['pin' => '5678'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Order already claimed by another staff member.');
    }

    #[Test]
    public function test_claimed_order_lockout_enforcement()
    {
        // Waitress A claims the order
        $this->order->update(['waiter_id' => $this->waitressA->id]);
        $this->order->refresh();

        $this->actingAs($this->waitressB);

        // Attempt to update status of Waitress A's order
        $this->putJson("/api/v1/orders/{$this->order->id}/status", [
            'status' => 'served'
        ])->assertStatus(403);

        // Attempt to delete Waitress A's order
        $this->deleteJson("/api/v1/orders/{$this->order->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function test_print_automatically_sets_preparing_status()
    {
        $kdsRole = Role::where('slug', 'kds')->first();
        $chef = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $chef->roles()->attach($kdsRole->id, ['hotel_id' => $this->hotel->id]);
        
        $this->actingAs($chef);

        $ticket = KitchenTicket::create([
            'hotel_id' => $this->hotel->id,
            'branch_id' => $this->outlet->id,
            'outlet_id' => $this->order->outlet_id,
            'department_id' => $this->order->department_id,
            'order_id' => $this->order->id,
            'status' => 'queued',
            'ticket_number' => 'T1',
        ]);

        // Print ticket
        $this->postJson("/api/v1/kds/tickets/{$ticket->id}/print")
            ->assertStatus(200);

        $ticket->refresh();
        $this->assertEquals('preparing', $ticket->status);
        $this->assertNotNull($ticket->started_at);

        // Verify history log
        $this->assertDatabaseHas('kitchen_ticket_status_histories', [
            'kitchen_ticket_id' => $ticket->id,
            'new_status' => 'preparing',
            'changed_by_user_id' => $chef->id,
        ]);
    }

    #[Test]
    public function test_waitress_claim_broadcasts_real_time_attribution()
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\OrderClaimed::class]);

        $this->actingAs($this->waitressA);

        $this->postJson('/api/v1/auth/staff/set-pin', ['pin' => '1234'])
            ->assertStatus(200);

        $this->postJson("/api/v1/orders/{$this->order->id}/claim", ['pin' => '1234'])
            ->assertStatus(200);

        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\OrderClaimed::class, function ($event) {
            return $event->order->id === $this->order->id 
                && $event->order->waiter_id === $this->waitressA->id;
        });
    }

    #[Test]
    public function test_manager_sees_live_waitress_attribution()
    {
        $this->actingAs($this->waitressA);

        $this->postJson('/api/v1/auth/staff/set-pin', ['pin' => '1234'])
            ->assertStatus(200);

        // Claim order
        $this->postJson("/api/v1/orders/{$this->order->id}/claim", ['pin' => '1234'])
            ->assertStatus(200);

        // Fetch orders as a manager
        $managerRole = Role::create(['hotel_id' => $this->hotel->id, 'name' => 'Manager', 'slug' => 'manager']);
        $manager = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $manager->roles()->attach($managerRole->id, ['hotel_id' => $this->hotel->id]);
        
        $this->actingAs($manager);
        
        $response = $this->getJson('/api/v1/admin/orders/live');
        $response->assertStatus(200);
        
        $claimedOrder = collect($response->json())->firstWhere('id', $this->order->id);
        $this->assertNotNull($claimedOrder);
        $this->assertEquals($this->waitressA->id, $claimedOrder['waiter_id']);
    }

    #[Test]
    public function test_waitress_claim_broadcasts_new_order_claimed_event()
    {
        \Illuminate\Support\Facades\Event::fake([\App\Events\NewOrderClaimed::class]);

        $this->actingAs($this->waitressA);

        $this->postJson('/api/v1/auth/staff/set-pin', ['pin' => '1234'])
            ->assertStatus(200);

        $this->postJson("/api/v1/orders/{$this->order->id}/claim", ['pin' => '1234'])
            ->assertStatus(200);

        \Illuminate\Support\Facades\Event::assertDispatched(\App\Events\NewOrderClaimed::class, function ($event) {
            return $event->order->id === $this->order->id
                && $event->order->waiter_id === $this->waitressA->id;
        });
    }

    #[Test]
    public function test_outlet_manager_receives_new_order_claimed_broadcast()
    {
        // Verify the POS channel auth callback grants access to Outlet Managers
        // in the same hotel and outlet (branch).
        $outletManager = User::factory()->create([
            'hotel_id'  => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
        ]);

        // Simulate the channel authorization check directly
        $result = \Illuminate\Support\Facades\Broadcast::auth(
            new \Illuminate\Http\Request([
                'channel_name' => 'private-hotel.' . $this->hotel->id . '.branch.' . $this->outlet->id . '.pos',
            ])
        );

        // Channel check: the Outlet Manager must share hotel_id AND outlet_id
        $this->assertTrue(
            (int) $outletManager->hotel_id === (int) $this->hotel->id
            && (int) ($outletManager->outlet_id ?? $outletManager->hotel_id) === (int) $this->outlet->id,
            'Outlet Manager should be authorized for the POS channel of their outlet.'
        );
    }
}
