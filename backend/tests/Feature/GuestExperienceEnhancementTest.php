<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\GuestPortalSession;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\MenuItem;
use App\Models\MenuCategory;

class GuestExperienceEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $room;
    protected $session;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->hotel = Hotel::create([
            'name' => 'Test Hotel',
            'slug' => 'test-hotel',
            'email' => 'test@example.com',
            'phone' => '1234567890'
        ]);

        $roomType = \App\Models\RoomType::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Deluxe Room',
            'slug' => 'deluxe-room',
            'base_price' => 200,
            'capacity' => 2
        ]);

        $this->room = Room::create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $roomType->id,
            'room_number' => '101',
            'status' => 'available',
            'housekeeping_status' => 'clean'
        ]);

        $this->department = \App\Models\Department::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Food & Beverage',
            'slug' => 'f-b'
        ]);
        
        $this->session = GuestPortalSession::create([
            'hotel_id' => $this->hotel->id,
            'context_type' => 'room',
            'context_id' => $this->room->id,
            'room_id' => $this->room->id,
            'session_token' => 'test-token-123',
            'expires_at' => now()->addHours(24),
            'is_active' => true,
        ]);
    }

    public function test_guest_can_track_order()
    {
        $outlet = Outlet::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Test Outlet',
            'type' => 'restaurant'
        ]);
        $order = Order::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $outlet->id,
            'department_id' => $this->department->id,
            'room_id' => $this->room->id,
            'order_number' => 'ORD-123',
            'status' => 'preparing',
            'total_amount' => 100,
            'order_source' => 'qr_room'
        ]);

        $response = $this->getJson("/api/v1/guest/orders/{$order->id}?session_token=test-token-123");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'preparing')
            ->assertJsonPath('order_number', 'ORD-123');
    }

    public function test_guest_can_get_recommendations()
    {
        $outlet = Outlet::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Food Outlet',
            'type' => 'restaurant'
        ]);
        $category = MenuCategory::create(['hotel_id' => $this->hotel->id, 'name' => 'Food']);
        MenuItem::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $outlet->id,
            'menu_category_id' => $category->id,
            'name' => 'Special Burger',
            'price' => 15,
            'is_active' => true,
            'is_available' => true,
            'is_featured' => true
        ]);

        $response = $this->getJson("/api/v1/guest/menu/{$outlet->id}/recommendations?session_token=test-token-123");

        $response->assertStatus(200)
            ->assertJsonStructure(['popular', 'chef_specials', 'perfect_pairings']);
    }

    public function test_guest_can_create_service_request()
    {
        $response = $this->postJson("/api/v1/guest/service-request", [
            'session_token' => 'test-token-123',
            'request_type' => 'housekeeping',
            'description' => 'Need extra pillows'
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Request created successfully.');

        $this->assertDatabaseHas('guest_service_requests', [
            'room_id' => $this->room->id,
            'request_type' => 'housekeeping',
            'description' => 'Need extra pillows'
        ]);
    }
}
