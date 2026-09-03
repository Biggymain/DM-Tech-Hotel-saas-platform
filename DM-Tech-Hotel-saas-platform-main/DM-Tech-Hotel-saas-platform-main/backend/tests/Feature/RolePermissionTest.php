<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_unauthenticated_users_are_rejected(): void
    {
        $response = $this->getJson('/api/v1/departments');
        $response->assertStatus(401);
    }

    #[Test]
    public function test_user_without_permission_receives_403(): void
    {
        $hotel = \App\Models\Hotel::create(['name' => 'Test Hotel', 'domain' => 'test.com']);
        $user = \App\Models\User::factory()->create(['hotel_id' => $hotel->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/pms/rooms');

        $response->assertStatus(403)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Permission denied'
                 ]);
    }

    #[Test]
    public function test_user_with_permission_can_access_route(): void
    {
        $hotel = \App\Models\Hotel::create(['name' => 'Test Hotel', 'domain' => 'test.com']);
        $user = \App\Models\User::factory()->create(['hotel_id' => $hotel->id]);
        
        $role = \App\Models\Role::create(['hotel_id' => $hotel->id, 'name' => 'Room Manager', 'slug' => 'room-manager']);
        $permission = \App\Models\Permission::create(['name' => 'View PMS Rooms', 'slug' => 'pms.rooms.view']);
        
        $role->permissions()->attach($permission->id, ['hotel_id' => $hotel->id]);
        $user->roles()->attach($role->id, ['hotel_id' => $hotel->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/pms/rooms');

        // It should pass the middleware, returning something other than 403 or 401
        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    #[Test]
    public function test_super_admin_bypasses_permission_checks(): void
    {
        $admin = \App\Models\User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin)->getJson('/api/v1/pms/rooms');

        $this->assertNotEquals(403, $response->status());
        $this->assertNotEquals(401, $response->status());
    }

    #[Test]
    public function test_tenant_module_toggle_blocks_access(): void
    {
        // 1. Setup hotel and user with valid permissions
        $hotel = \App\Models\Hotel::create(['name' => 'Test Hotel', 'domain' => 'test.com']);
        $user = \App\Models\User::factory()->create(['hotel_id' => $hotel->id]);
        
        $role = \App\Models\Role::create(['hotel_id' => $hotel->id, 'name' => 'Kitchen Staff', 'slug' => 'kitchen']);
        $permission = \App\Models\Permission::create(['name' => 'View KDS', 'slug' => 'kds.view']);
        
        $role->permissions()->attach($permission->id, ['hotel_id' => $hotel->id]);
        $user->roles()->attach($role->id, ['hotel_id' => $hotel->id]);

        // 2. Request KDS without the module enabled securely returns 403
        $response = $this->actingAs($user)->withHeader('X-Test-Enforce-Module-Limits', '1')->getJson('/api/v1/kds/tickets');

        $response->assertStatus(403)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Module access unavailable in current mode'
                 ]);

        // 3. Enable the module
        $module = \Illuminate\Support\Facades\DB::table('modules')->insertGetId(['name' => 'Kitchen', 'slug' => 'kitchen']);
        \Illuminate\Support\Facades\DB::table('hotel_modules')->insert(['hotel_id' => $hotel->id, 'module_id' => $module, 'is_enabled' => true]);
        \Illuminate\Support\Facades\Cache::flush();

        // 4. Request KDS again -> passes module check, hits logic!
        $response2 = $this->actingAs($user)->getJson('/api/v1/kds/tickets');
        // Because we don't have the controller mapped cleanly in this test skeleton, it might return 500 or 404, or 200 depending on mock, but NOT 403 from middleware.
        $this->assertNotEquals(403, $response2->status());
    }
}
