<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_fetch_roles_for_hotel()
    {
        $hotel = Hotel::factory()->create();
        $user = User::factory()->create(['hotel_id' => $hotel->id]);
        
        Role::create(['hotel_id' => $hotel->id, 'name' => 'Manager', 'slug' => 'manager']);
        Role::create(['hotel_id' => $hotel->id, 'name' => 'Staff', 'slug' => 'staff']);
        Role::create(['hotel_id' => null, 'name' => 'System Admin', 'slug' => 'sys-admin', 'is_system_role' => true]);

        $response = $this->actingAs($user)->getJson('/api/v1/roles');

        $response->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonFragment(['name' => 'Manager'])
            ->assertJsonFragment(['name' => 'Staff'])
            ->assertJsonFragment(['name' => 'System Admin']);
    }

    public function test_cannot_fetch_other_hotel_roles()
    {
        $hotel1 = Hotel::factory()->create();
        $hotel2 = Hotel::factory()->create();
        $user1 = User::factory()->create(['hotel_id' => $hotel1->id]);
        
        Role::create(['hotel_id' => $hotel1->id, 'name' => 'Manager 1', 'slug' => 'manager-1']);
        Role::create(['hotel_id' => $hotel2->id, 'name' => 'Manager 2', 'slug' => 'manager-2']);

        $response = $this->actingAs($user1)->getJson('/api/v1/roles');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Manager 1'])
            ->assertJsonMissing(['name' => 'Manager 2']);
    }
}
