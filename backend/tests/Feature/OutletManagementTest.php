<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Outlet;
use App\Models\MenuItem;
use App\Models\MenuCategory;
use App\Models\User;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutletManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $manager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->hotel = Hotel::create(['name' => 'Paradise Hotel']);
        
        // Use proper relationship for roles
        $this->manager = User::factory()->create([
            'hotel_id' => $this->hotel->id
        ]);
        
        $role = \App\Models\Role::create([
            'name' => 'General Manager',
            'slug' => 'general-manager',
            'hotel_id' => $this->hotel->id
        ]);

        $permission = \App\Models\Permission::create([
            'name' => 'Create Menu',
            'slug' => 'menu.create'
        ]);

        $role->permissions()->attach($permission->id);
        
        $this->manager->roles()->attach($role->id, ['hotel_id' => $this->hotel->id]);
        
        $this->actingAs($this->manager);
    }

    public function test_can_create_outlet()
    {
        $payload = [
            'name' => 'Beach Bar',
            'type' => 'bar',
            'is_active' => true
        ];

        $response = $this->postJson('/api/v1/outlets', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('outlets', ['name' => 'Beach Bar', 'hotel_id' => $this->hotel->id]);
    }

    public function test_can_duplicate_item_to_outlet()
    {
        $store = Outlet::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Main Store',
            'type' => 'store'
        ]);

        $bar = Outlet::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Pool Bar',
            'type' => 'bar'
        ]);

        $category = MenuCategory::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Drinks'
        ]);

        $item = MenuItem::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $store->id,
            'menu_category_id' => $category->id,
            'name' => 'Heineken',
            'price' => 500,
            'is_available' => true
        ]);

        $payload = [
            'outlet_id' => $bar->id,
            'price' => 800
        ];

        $response = $this->postJson("/api/v1/menu/items/{$item->id}/duplicate", $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('menu_items', [
            'name' => 'Heineken',
            'outlet_id' => $bar->id,
            'price' => 800
        ]);
        
        // Ensure original remains
        $this->assertDatabaseHas('menu_items', [
            'name' => 'Heineken',
            'outlet_id' => $store->id,
            'price' => 500
        ]);
    }
}
