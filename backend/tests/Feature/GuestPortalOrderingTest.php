<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Hotel;
use App\Models\Outlet;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\GuestPortalSession;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class GuestPortalOrderingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_guest_can_fetch_outlet_menu()
    {
        $hotel = Hotel::factory()->create();
        $outlet = Outlet::create(['hotel_id' => $hotel->id, 'name' => 'Pool Bar', 'type' => 'bar']);
        
        $category = MenuCategory::create(['hotel_id' => $hotel->id, 'name' => 'Drinks', 'display_order' => 1]);
        $department = \App\Models\Department::create(['hotel_id' => $hotel->id, 'name' => 'F&B', 'slug' => 'f-and-b']);
        
        MenuItem::create([
            'hotel_id' => $hotel->id,
            'outlet_id' => $outlet->id,
            'department_id' => $department->id,
            'menu_category_id' => $category->id,
            'name' => 'Mojito',
            'price' => 12.00,
            'is_active' => true,
            'is_available' => true,
        ]);

        $session = GuestPortalSession::create([
            'hotel_id' => $hotel->id,
            'context_type' => 'table',
            'context_id' => 5,
            'session_token' => Str::random(64),
            'expires_at' => now()->addHours(24),
            'is_active' => true,
        ]);

        $response = $this->withHeaders(['X-Guest-Session' => $session->session_token])
            ->getJson("/api/v1/guest/menu/{$outlet->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['outlet', 'categories'])
            ->assertJsonPath('categories.0.items.0.name', 'Mojito');
    }

    #[Test]
    public function test_guest_can_place_order()
    {
        $hotel = Hotel::factory()->create();
        $outlet = Outlet::create(['hotel_id' => $hotel->id, 'name' => 'Pool Bar', 'type' => 'bar']);
        $category = MenuCategory::create(['hotel_id' => $hotel->id, 'name' => 'Drinks', 'display_order' => 1]);
        $department = \App\Models\Department::create(['hotel_id' => $hotel->id, 'name' => 'F&B', 'slug' => 'f-and-b']);
        
        $menuItem = MenuItem::create([
            'hotel_id' => $hotel->id,
            'outlet_id' => $outlet->id,
            'department_id' => $department->id,
            'menu_category_id' => $category->id,
            'name' => 'Mojito',
            'price' => 12.00,
            'is_active' => true,
            'is_available' => true,
        ]);

        $session = GuestPortalSession::create([
            'hotel_id' => $hotel->id,
            'context_type' => 'table',
            'context_id' => 12,
            'session_token' => Str::random(64),
            'expires_at' => now()->addHours(24),
            'is_active' => true,
        ]);

        $response = $this->withHeaders(['X-Guest-Session' => $session->session_token])
            ->postJson("/api/v1/guest/orders/{$outlet->id}", [
                'items' => [
                    [
                        'menu_item_id' => $menuItem->id,
                        'quantity' => 2,
                        'notes' => 'Extra ice',
                    ]
                ]
            ]);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Order created successfully. Please proceed to payment.']);

        $this->assertDatabaseHas('orders', [
            'hotel_id' => $hotel->id,
            'outlet_id' => $outlet->id,
            'table_number' => '12',
            'order_source' => 'qr_table',
            'total_amount' => 24.00,
        ]);

        $this->assertDatabaseHas('order_items', [
            'menu_item_id' => $menuItem->id,
            'quantity' => 2,
            'notes' => 'Extra ice',
        ]);
    }
}
