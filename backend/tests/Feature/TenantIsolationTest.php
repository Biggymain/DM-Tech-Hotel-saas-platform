<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\HotelGroup;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

        // Seed some basic roles
        Role::create(['name' => 'Hotel Owner', 'slug' => 'hotelowner', 'is_system_role' => true]);
        Role::create(['name' => 'Group Admin', 'slug' => 'group-admin', 'is_system_role' => true]);
    }

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
    public function test_cross_tenant_is_blocked_on_basemodel()
    {
        $hotelA = Hotel::create(['name' => 'Hotel A', 'domain' => 'a.com']);
        $hotelB = Hotel::create(['name' => 'Hotel B', 'domain' => 'b.com']);
        $branchA = \App\Models\Outlet::create(['hotel_id' => $hotelA->id, 'name' => 'Branch A']);
        $branchB = \App\Models\Outlet::create(['hotel_id' => $hotelB->id, 'name' => 'Branch B']);
        $deptA = \App\Models\Department::create(['hotel_id' => $hotelA->id, 'name' => 'Dept', 'slug' => 'dept-a']);
        $deptB = \App\Models\Department::create(['hotel_id' => $hotelB->id, 'name' => 'Dept', 'slug' => 'dept-b']);
        
        // Strict boundary: $userA should never see HotelB's BaseModel derived data!
        $userA = User::factory()->create(['hotel_id' => $hotelA->id]);
        
        \App\Models\Order::create(['hotel_id' => $hotelB->id, 'outlet_id' => $branchB->id, 'department_id' => $deptB->id, 'order_number' => 'B123', 'total_amount' => 100]);
        \App\Models\Order::create(['hotel_id' => $hotelA->id, 'outlet_id' => $branchA->id, 'department_id' => $deptA->id, 'order_number' => 'A123', 'total_amount' => 100]);

        // Boot eloquent scope automatically via actingAs
        $this->actingAs($userA);
        
        $orders = \App\Models\Order::all();
        $this->assertCount(1, $orders);
        $this->assertEquals('A123', $orders->first()->order_number);
    }
    
    #[Test]
    public function test_cross_branch_is_blocked_on_basemodel()
    {
        $hotel = Hotel::create(['name' => 'Hotel', 'domain' => 'aa.com']);
        $branch1 = \App\Models\Outlet::create(['hotel_id' => $hotel->id, 'name' => 'Branch 1']);
        $branch2 = \App\Models\Outlet::create(['hotel_id' => $hotel->id, 'name' => 'Branch 2']);
        $dept = \App\Models\Department::create(['hotel_id' => $hotel->id, 'name' => 'Dept', 'slug' => 'dept-main']);
        
        // Strict Boundary: $userBranch1 cannot see queries derived from Branch 2 globally
        $userBranch1 = User::factory()->create(['hotel_id' => $hotel->id, 'outlet_id' => $branch1->id]);
        
        \App\Models\Order::create(['hotel_id' => $hotel->id, 'outlet_id' => $branch1->id, 'department_id' => $dept->id, 'order_number' => 'Branch1Ord', 'total_amount' => 50]);
        \App\Models\Order::create(['hotel_id' => $hotel->id, 'outlet_id' => $branch2->id, 'department_id' => $dept->id, 'order_number' => 'Branch2Ord', 'total_amount' => 50]);

        $this->actingAs($userBranch1);
        
        $orders = \App\Models\Order::all();
        $this->assertCount(1, $orders);
        $this->assertEquals('Branch1Ord', $orders->first()->order_number);
    }

    #[Test]
    public function test_exceptional_roles_bypass_branch_but_respect_tenant_on_basemodel()
    {
        $group = HotelGroup::create(['name' => 'My Group', 'slug' => 'my-group']);
        $hotelInGroup = Hotel::create(['hotel_group_id' => $group->id, 'name' => 'Hotel In', 'domain' => 'in.com']);
        $hotelOutGroup = Hotel::create(['name' => 'Hotel Out', 'domain' => 'out.com']);
        
        $branch1 = \App\Models\Outlet::create(['hotel_id' => $hotelInGroup->id, 'name' => 'Branch 1']);
        $branch2 = \App\Models\Outlet::create(['hotel_id' => $hotelInGroup->id, 'name' => 'Branch 2']);
        $branchOut = \App\Models\Outlet::create(['hotel_id' => $hotelOutGroup->id, 'name' => 'Branch Out']);
        $deptIn = \App\Models\Department::create(['hotel_id' => $hotelInGroup->id, 'name' => 'Dept', 'slug' => 'dept-in']);
        $deptOut = \App\Models\Department::create(['hotel_id' => $hotelOutGroup->id, 'name' => 'Dept', 'slug' => 'dept-out']);
        
        $adminRole = Role::create(['hotel_id' => null, 'name' => 'Group Admin', 'slug' => 'group_admin']);
        $groupAdmin = User::factory()->create(['hotel_group_id' => $group->id, 'hotel_id' => null]);
        $groupAdmin->roles()->attach($adminRole->id);

        \App\Models\Order::create(['hotel_id' => $hotelInGroup->id, 'outlet_id' => $branch1->id, 'department_id' => $deptIn->id, 'order_number' => 'I-1', 'total_amount' => 1]);
        \App\Models\Order::create(['hotel_id' => $hotelInGroup->id, 'outlet_id' => $branch2->id, 'department_id' => $deptIn->id, 'order_number' => 'I-2', 'total_amount' => 1]);
        \App\Models\Order::create(['hotel_id' => $hotelOutGroup->id, 'outlet_id' => $branchOut->id, 'department_id' => $deptOut->id, 'order_number' => 'O-1', 'total_amount' => 1]);

        $this->actingAs($groupAdmin);
        
        $orders = \App\Models\Order::all();

        // 2 branches internal to their group visible -> 1 outsider data block invisible 
        $this->assertCount(2, $orders);
        $ids = $orders->pluck('order_number')->toArray();
        $this->assertTrue(in_array('I-1', $ids));
        $this->assertTrue(in_array('I-2', $ids));
    }
}
