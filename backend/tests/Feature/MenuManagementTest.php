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
use App\Models\Modifier;
use App\Models\ModifierOption;
use Laravel\Sanctum\Sanctum;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $user;
    protected $outlet;
    protected $department;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable mass assignment just for tests
        MenuCategory::unguard();
        MenuItem::unguard();
        Modifier::unguard();
        ModifierOption::unguard();

        $this->hotel = Hotel::create([
            'name' => 'Menu Test Hotel',
            'domain' => 'menu-test-hotel',
            'is_active' => true,
        ]);

        $role = Role::create([
            'name' => 'Hotel Owner', 
            'slug' => 'hotelowner', 
            'hotel_id' => $this->hotel->id
        ]);
        
        $permissions = [
            'menu.view',
            'menu.create',
            'menu.update',
            'menu.delete',
        ];

        foreach ($permissions as $permName) {
            $perm = \App\Models\Permission::create([
                'name' => ucfirst(str_replace('.', ' ', $permName)),
                'slug' => $permName,
                'module' => 'Menu',
                'hotel_id' => $this->hotel->id
            ]);
            $role->permissions()->attach($perm->id, ['hotel_id' => $this->hotel->id]);
        }
        
        $this->user = User::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Owner',
            'email' => 'menuowner@test.com',
            'password' => bcrypt('password'),
            'is_super_admin' => false,
        ]);

        $this->user->roles()->attach($role->id, ['hotel_id' => $this->hotel->id]);
        
        // Clear cache and authenticate the freshly updated user instance
        app(\App\Services\PermissionService::class)->clearPermissionCache($this->user);
        
        $this->user = User::with('roles.permissions')->find($this->user->id);
        Sanctum::actingAs($this->user, ['*']);

        $this->outlet = Outlet::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Main Restaurant',
            'type' => 'restaurant'
        ]);

        $this->department = Department::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'name' => 'Kitchen',
            'slug' => 'kitchen'
        ]);
    }

    public function test_can_create_menu_category()
    {
        $payload = [
            'outlet_id' => $this->outlet->id,
            'name' => 'Starters',
            'description' => 'Delicious starters',
            'is_active' => true,
            'display_order' => 1
        ];

        $response = $this->postJson('/api/v1/menu/categories', $payload);

        $response->assertStatus(201)
                 ->assertJsonFragment(['name' => 'Starters']);

        $this->assertDatabaseHas('menu_categories', [
            'hotel_id' => $this->hotel->id,
            'name' => 'Starters',
            'display_order' => 1
        ]);
    }

    public function test_can_create_menu_item_with_department_routing()
    {
        $category = MenuCategory::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Desserts'
        ]);

        $payload = [
            'outlet_id' => $this->outlet->id,
            'menu_category_id' => $category->id,
            'department_id' => $this->department->id,
            'name' => 'Chocolate Cake',
            'description' => 'Rich chocolate cake',
            'price' => 12.50,
            'cost_price' => 4.00,
            'is_available' => true,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/v1/menu/items', $payload);

        $response->assertStatus(201)
                 ->assertJsonFragment([
                     'name' => 'Chocolate Cake',
                     'department_id' => $this->department->id
                 ]);

        $this->assertDatabaseHas('menu_items', [
            'hotel_id' => $this->hotel->id,
            'name' => 'Chocolate Cake',
            'department_id' => $this->department->id
        ]);
        
        // Ensure price formatting is correct in database retrieval
        $item = MenuItem::where('name', 'Chocolate Cake')->first();
        $this->assertEquals(12.50, $item->price);
    }

    public function test_can_create_modifier_and_attach_to_menu_item()
    {
        // 1. Create Modifier with Options
        $modifierPayload = [
            'name' => 'Meat Temperature',
            'min_selections' => 1,
            'max_selections' => 1,
            'options' => [
                ['name' => 'Rare', 'price_adjustment' => 0],
                ['name' => 'Medium', 'price_adjustment' => 0],
                ['name' => 'Well Done', 'price_adjustment' => 0]
            ]
        ];

        $modResponse = $this->postJson('/api/v1/menu/modifiers', $modifierPayload);
        $modResponse->assertStatus(201);
        $modifierId = $modResponse->json('id');

        $this->assertDatabaseHas('modifiers', ['name' => 'Meat Temperature']);
        $this->assertDatabaseHas('modifier_options', ['name' => 'Rare', 'modifier_id' => $modifierId]);

        // 2. Create Menu Item and Attach Modifier
        $itemPayload = [
            'name' => 'Steak',
            'price' => 25.00,
            'modifier_ids' => [$modifierId]
        ];

        $itemResponse = $this->postJson('/api/v1/menu/items', $itemPayload);
        $itemResponse->assertStatus(201);
        $itemId = $itemResponse->json('id');

        // Verify attachment via pivot
        $this->assertDatabaseHas('menu_item_modifiers', [
            'menu_item_id' => $itemId,
            'modifier_id' => $modifierId
        ]);
    }

    public function test_menu_items_are_isolated_by_tenant()
    {
        // Hotel 1 Item
        MenuItem::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Hotel 1 Item',
            'price' => 10.00
        ]);

        // Hotel 2 Item setup
        $hotel2 = Hotel::create(['name' => 'Hotel 2', 'domain' => 'hotel-2', 'is_active' => true]);
        MenuItem::create([
            'hotel_id' => $hotel2->id,
            'name' => 'Hotel 2 Item',
            'price' => 15.00
        ]);

        $response = $this->getJson('/api/v1/menu/items');

        $response->assertStatus(200)
                 ->assertJsonCount(1)
                 ->assertJsonFragment(['name' => 'Hotel 1 Item'])
                 ->assertJsonMissing(['name' => 'Hotel 2 Item']);
    }

    public function test_tenant_isolation_prevents_unauthorized_updates()
    {
        // Acting as admin of Hotel 1 (implicit from setUp), trying to update Hotel 2's item
        $hotel2 = Hotel::create(['name' => 'Hotel 2', 'domain' => 'hotel-2', 'is_active' => true]);
        
        $itemH2 = MenuItem::create([
            'hotel_id' => $hotel2->id,
            'name' => 'Secret Recipe',
            'price' => 100.00
        ]);

        $response = $this->putJson("/api/v1/menu/items/{$itemH2->id}", [
            'price' => 50.00
        ]);

        $response->assertStatus(404); // Using TenantScope, should not find cross-tenant item
    }
}
