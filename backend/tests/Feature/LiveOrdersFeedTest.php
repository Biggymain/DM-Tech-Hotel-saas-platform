<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Hotel;
use App\Models\Order;
use App\Models\Department;
use App\Models\Outlet;

class LiveOrdersFeedTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $admin;
    protected $dept;
    protected $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::create(['name' => 'Main Hotel', 'email' => 'main@test.com']);
        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'hotel_id' => $this->hotel->id,
        ]);

        $this->dept = Department::create(['hotel_id' => $this->hotel->id, 'name' => 'F&B', 'slug' => 'f-b']);
        $this->outlet = Outlet::create(['hotel_id' => $this->hotel->id, 'name' => 'Cafe', 'type' => 'restaurant', 'slug' => 'cafe']);
    }

    public function test_admin_can_see_live_guest_orders()
    {
        // Guest Order
        Order::create([
            'hotel_id' => $this->hotel->id,
            'order_number' => 'G-1',
            'order_source' => 'room_service',
            'status' => 'pending',
            'total_amount' => 50,
            'department_id' => $this->dept->id,
            'outlet_id' => $this->outlet->id
        ]);

        // POS Order (Exclude from live feed but include in pos feed)
        Order::create([
            'hotel_id' => $this->hotel->id,
            'order_number' => 'P-1',
            'order_source' => 'pos',
            'status' => 'confirmed',
            'total_amount' => 30,
            'department_id' => $this->dept->id,
            'outlet_id' => $this->outlet->id
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/orders/live');

        $response->assertStatus(200)
            ->assertJsonCount(1);
    }
}
