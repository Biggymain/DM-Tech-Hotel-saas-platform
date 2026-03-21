<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_users_are_rejected(): void
    {
        $response = $this->getJson('/api/v1/hotels');
        $response->assertStatus(401);
    }

    public function test_user_without_permission_receives_403(): void
    {
        $hotel = \App\Models\Hotel::create(['name' => 'Test Hotel', 'domain' => 'test.com']);
        $user = \App\Models\User::factory()->create(['hotel_id' => $hotel->id]);

        $response = $this->actingAs($user)->getJson('/api/v1/rooms');

        $response->assertStatus(403)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Permission denied'
                 ]);
    }

    public function test_user_with_permission_can_access_route(): void
    {
        $hotel = \App\Models\Hotel::create(['name' => 'Test Hotel', 'domain' => 'test.com']);
        $user = \App\Models\User::factory()->create(['hotel_id' => $hotel->id]);
        
        $role = \App\Models\Role::create(['hotel_id' => $hotel->id, 'name' => 'Room Manager', 'slug' => 'room-manager']);
        $permission = \App\Models\Permission::create(['name' => 'Manage Rooms', 'slug' => 'rooms.manage']);
        
        $role->permissions()->attach($permission->id, ['hotel_id' => $hotel->id]);
        $user->roles()->attach($role->id, ['hotel_id' => $hotel->id]);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $response = $this->actingAs($user)->getJson('/api/v1/rooms');
        \Illuminate\Support\Facades\Log::info('Queries executed:', \Illuminate\Support\Facades\DB::getQueryLog());

        // It should pass the middleware, returning the placeholder 200 response
        $response->assertStatus(200)
                 ->assertJson(['message' => 'Rooms accessed']);
    }

    public function test_super_admin_bypasses_permission_checks(): void
    {
        $admin = \App\Models\User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin)->getJson('/api/v1/finance');

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Finance accessed']);
    }
}
