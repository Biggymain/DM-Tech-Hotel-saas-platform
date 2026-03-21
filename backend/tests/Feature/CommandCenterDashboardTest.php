<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\Order;
use App\Models\Department;
use App\Models\Outlet;
use App\Models\RoomType;

class CommandCenterDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected $hotelA;
    protected $hotelB;
    protected $adminA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotelA = Hotel::create(['name' => 'Hotel A', 'email' => 'a@test.com']);
        $this->hotelB = Hotel::create(['name' => 'Hotel B', 'email' => 'b@test.com']);

        $this->adminA = User::create([
            'name' => 'Admin A',
            'email' => 'admin_a@test.com',
            'password' => bcrypt('password'),
            'hotel_id' => $this->hotelA->id,
        ]);

        $dept = Department::create(['hotel_id' => $this->hotelA->id, 'name' => 'Housekeeping', 'slug' => 'housekeeping']);
        $outlet = Outlet::create(['hotel_id' => $this->hotelA->id, 'name' => 'Main Restaurant', 'type' => 'restaurant', 'slug' => 'main-restaurant']);
        
        $roomTypeA = RoomType::create(['hotel_id' => $this->hotelA->id, 'name' => 'Standard', 'slug' => 'standard-a', 'base_price' => 100]);
        $roomTypeB = RoomType::create(['hotel_id' => $this->hotelB->id, 'name' => 'Standard', 'slug' => 'standard-b', 'base_price' => 100]);

        // Data for Hotel A
        Room::create(['hotel_id' => $this->hotelA->id, 'room_number' => '101', 'status' => 'occupied', 'housekeeping_status' => 'clean', 'room_type_id' => $roomTypeA->id]);
        Room::create(['hotel_id' => $this->hotelA->id, 'room_number' => '102', 'status' => 'available', 'housekeeping_status' => 'dirty', 'room_type_id' => $roomTypeA->id]);
        
        Order::create([
            'hotel_id' => $this->hotelA->id,
            'total_amount' => 100,
            'payment_status' => 'paid',
            'order_source' => 'pos',
            'order_number' => 'ORD-1',
            'department_id' => $dept->id,
            'outlet_id' => $outlet->id
        ]);

        // Data for Hotel B (Isolation Test)
        Room::create(['hotel_id' => $this->hotelB->id, 'room_number' => '201', 'status' => 'occupied', 'housekeeping_status' => 'clean', 'room_type_id' => $roomTypeB->id]);
    }

    public function test_admin_can_access_occupancy_summary_with_isolation()
    {
        $response = $this->actingAs($this->adminA, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/occupancy');

        $response->assertStatus(200)
            ->assertJson([
                'total_rooms' => 2,
                'occupied_rooms' => 1,
            ]);
            
        // Confirm Hotel B room is NOT included
        $this->assertNotEquals(3, $response->json('total_rooms'));
    }

    public function test_admin_can_access_revenue_summary_with_isolation()
    {
        $response = $this->actingAs($this->adminA, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/revenue');

        $response->assertStatus(200)
            ->assertJson([
                'today_revenue' => 100.0,
            ]);
    }
}
