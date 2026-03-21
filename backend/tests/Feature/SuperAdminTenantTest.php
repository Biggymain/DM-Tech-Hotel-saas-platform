<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\HotelGroup;
use App\Models\User;
use App\Models\RoomType;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed some basic roles and permissions for pms.rooms.view (since some controllers check roles)
        $perm = Permission::create(['name' => 'View Rooms', 'slug' => 'pms.rooms.view']);
        $role = Role::create(['name' => 'Super Admin', 'slug' => 'superadmin', 'is_system_role' => true]);
        $role->permissions()->attach($perm->id);
    }

    public function test_super_admin_can_switch_hotel_context_via_header(): void
    {
        $group = HotelGroup::create(['name' => 'Group A', 'slug' => 'group-a']);
        $hotel = Hotel::create(['name' => 'Hotel 1', 'hotel_group_id' => $group->id, 'domain' => 'h1.local']);
        
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@dmtech.local',
            'password' => 'password',
            'is_super_admin' => true
        ]);

        $roomType = RoomType::create([
            'hotel_id' => $hotel->id,
            'name' => 'Deluxe',
            'base_price' => 100,
            'capacity' => 2
        ]);

        // Request with context header
        $response = $this->actingAs($superAdmin)
            ->withHeader('X-Hotel-Context', $hotel->id)
            ->getJson('/api/v1/pms/room-types');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($roomType->id, $response->json('data.0.id'));
        $this->assertEquals($hotel->id, app('tenant_id'));
    }

    public function test_super_admin_without_context_allowed_by_middleware(): void
    {
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@dmtech.local',
            'password' => 'password',
            'is_super_admin' => true
        ]);

        // Request without context header should NOT return 403 for Super Admin anymore
        $response = $this->actingAs($superAdmin)
            ->getJson('/api/v1/pms/room-types');

        $response->assertStatus(200);
    }
}
