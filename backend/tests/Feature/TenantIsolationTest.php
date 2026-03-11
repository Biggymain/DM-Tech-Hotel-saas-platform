<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Role;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_isolation_global_scope()
    {
        // Disable mass assignment protection for easy testing setup
        User::unguard();
        Hotel::unguard();
        Room::unguard();
        Role::unguard();

        Role::create(['id' => 1, 'name' => 'SuperAdmin', 'slug' => 'superadmin']);
        Role::create(['id' => 2, 'name' => 'HotelOwner', 'slug' => 'hotelowner']);

        $hotelA = Hotel::create([
            'name' => 'Hotel A',
            'domain' => 'hotel-a',
            'is_active' => true,
        ]);
        
        $hotelB = Hotel::create([
            'name' => 'Hotel B',
            'domain' => 'hotel-b',
            'is_active' => true,
        ]);

        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@admin.com',
            'password' => bcrypt('password'),
            'is_super_admin' => true,
        ]);
        $superAdminRole = Role::where('slug', 'superadmin')->first();
        $superAdmin->roles()->attach($superAdminRole->id, ['hotel_id' => null]);

        $userA = User::create([
            'name' => 'User A',
            'email' => 'user@a.com',
            'password' => bcrypt('password'),
            'hotel_id' => $hotelA->id,
            'is_super_admin' => false,
        ]);
        $hotelOwnerRole = Role::where('slug', 'hotelowner')->first();
        $userA->roles()->attach($hotelOwnerRole->id, ['hotel_id' => $hotelA->id]);

        $userB = User::create([
            'name' => 'User B',
            'email' => 'user@b.com',
            'password' => bcrypt('password'),
            'hotel_id' => $hotelB->id,
            'is_super_admin' => false,
        ]);
        $userB->roles()->attach($hotelOwnerRole->id, ['hotel_id' => $hotelB->id]);

        // Acting as user A, creating a room should auto-assign hotel_id globally
        $this->actingAs($userA);
        $roomTypeA = RoomType::create(['hotel_id' => $hotelA->id, 'name' => 'Standard A', 'base_price' => 100]);
        $roomA = Room::create(['room_type_id' => $roomTypeA->id, 'room_number' => '101']);
        $this->assertEquals($hotelA->id, $roomA->hotel_id);
        
        // Fetch rooms for A, should only see 1
        $this->assertCount(1, Room::all());

        // Acting as user B, creating a room should auto-assign B's hotel_id
        $this->actingAs($userB);
        $roomTypeB = RoomType::create(['hotel_id' => $hotelB->id, 'name' => 'Standard B', 'base_price' => 100]);
        $roomB = Room::create(['room_type_id' => $roomTypeB->id, 'room_number' => '201']);
        $this->assertEquals($hotelB->id, $roomB->hotel_id);
        
        // Fetch rooms for B, should only see 1
        $this->assertCount(1, Room::all());

        // Acting as SuperAdmin, fetching rooms should reveal all
        $this->actingAs($superAdmin);
        $this->assertCount(2, Room::all());
    }

    public function test_tenant_isolation_middleware()
    {
        User::unguard();
        Role::unguard();
        
        Role::create(['id' => 2, 'name' => 'HotelOwner', 'slug' => 'hotelowner']);
        
        $userNoTenant = User::create([
            'name' => 'No Tenant',
            'email' => 'no@tenant.com',
            'password' => bcrypt('password'),
            'hotel_id' => null, // Or omit
            'is_super_admin' => false,
        ]);
        $userNoTenant->roles()->attach(2, ['hotel_id' => null]);

        $this->actingAs($userNoTenant);
        
        // Assuming /api/v1/hotels requires tenant
        $response = $this->getJson('/api/v1/hotels');
        
        $response->assertStatus(403)
                 ->assertJsonPath('error', 'Unauthorized by Tenant Manager');
    }
}
