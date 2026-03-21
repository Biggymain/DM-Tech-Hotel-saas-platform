<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\HotelGroup;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed some basic roles
        Role::create(['name' => 'Hotel Owner', 'slug' => 'hotelowner', 'is_system_role' => true]);
        Role::create(['name' => 'Group Admin', 'slug' => 'group-admin', 'is_system_role' => true]);
    }

    public function test_branch_user_cannot_see_other_hotels(): void
    {
        $group = HotelGroup::create(['name' => 'Group A', 'slug' => 'group-a']);
        $hotel1 = Hotel::create(['name' => 'Hotel 1', 'hotel_group_id' => $group->id, 'domain' => 'h1.local']);
        $hotel2 = Hotel::create(['name' => 'Hotel 2', 'hotel_group_id' => $group->id, 'domain' => 'h2.local']);

        $user = User::create([
            'name' => 'Staff 1',
            'email' => 'staff1@h1.local',
            'password' => 'password',
            'hotel_id' => $hotel1->id,
            'hotel_group_id' => $group->id
        ]);

        $this->actingAs($user);

        // Should only see Hotel 1
        $hotels = Hotel::all();
        $this->assertCount(1, $hotels);
        $this->assertEquals($hotel1->id, $hotels->first()->id);
    }

    public function test_group_admin_cannot_see_other_groups_hotels(): void
    {
        $groupA = HotelGroup::create(['name' => 'Group A', 'slug' => 'group-a']);
        $groupB = HotelGroup::create(['name' => 'Group B', 'slug' => 'group-b']);

        $hotelA = Hotel::create(['name' => 'Hotel A', 'hotel_group_id' => $groupA->id, 'domain' => 'ha.local']);
        $hotelB = Hotel::create(['name' => 'Hotel B', 'hotel_group_id' => $groupB->id, 'domain' => 'hb.local']);

        $adminA = User::create([
            'name' => 'Admin A',
            'email' => 'admin@groupa.local',
            'password' => 'password',
            'hotel_group_id' => $groupA->id,
            'hotel_id' => null
        ]);

        $this->actingAs($adminA);

        // Should only see Hotel A
        $hotels = Hotel::all();
        $this->assertCount(1, $hotels);
        $this->assertEquals($hotelA->id, $hotels->first()->id);
        
        // Should NOT see Hotel B even by ID (global scope should catch it)
        $this->assertNull(Hotel::find($hotelB->id));
    }

    public function test_super_admin_can_see_everything(): void
    {
        $group = HotelGroup::create(['name' => 'Group A', 'slug' => 'group-a']);
        $hotel1 = Hotel::create(['name' => 'Hotel 1', 'hotel_group_id' => $group->id, 'domain' => 'h1.local']);
        
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'super@dmtech.local',
            'password' => 'password',
            'is_super_admin' => true
        ]);

        $this->actingAs($superAdmin);

        $this->assertCount(1, Hotel::all());
    }
}
