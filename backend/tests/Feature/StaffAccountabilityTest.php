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

class StaffAccountabilityTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $waitressA;
    protected $waitressB;
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
        $outlet = Outlet::factory()->create(['hotel_id' => $this->hotel->id]);

        $this->order = Order::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $outlet->id,
            'department_id' => $department->id,
            'order_number' => 'ORD-TEST-123',
            'order_status' => 'pending',
            'total_amount' => 100,
            'order_source' => 'guest_qr',
        ]);
    }

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
    public function test_print_automatically_sets_preparing_status()
    {
        $kdsRole = Role::where('slug', 'kds')->first();
        $chef = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $chef->roles()->attach($kdsRole->id, ['hotel_id' => $this->hotel->id]);
        
        $this->actingAs($chef);

        $ticket = KitchenTicket::create([
            'hotel_id' => $this->hotel->id,
            'branch_id' => $this->hotel->id,
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
}
