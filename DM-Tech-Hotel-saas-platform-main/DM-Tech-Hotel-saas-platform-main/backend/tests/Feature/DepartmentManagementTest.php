<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Hotel;
use App\Models\User;
use App\Models\Role;
use App\Models\Outlet;
use App\Models\Department;
use App\Models\Permission;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;

class DepartmentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $user;
    protected $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Tenant
        $this->hotel = Hotel::create([
            'name' => 'Test Hotel',
            'domain' => 'test-hotel',
            'is_active' => true,
        ]);

        // 2. Setup SuperAdmin for bypass or HotelOwner
        $role = Role::create(['name' => 'Hotel Owner', 'slug' => 'hotelowner']);
        
        $this->user = User::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'is_super_admin' => true,
        ]);

        $this->user->roles()->attach($role->id, ['hotel_id' => $this->hotel->id]);

        // 3. Setup Outlet
        $this->outlet = Outlet::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Main Restaurant',
            'type' => 'restaurant'
        ]);
        
        Sanctum::actingAs($this->user, ['*']);
    }

    #[Test]
    public function test_can_create_department_with_automatic_permissions()
    {
        $payload = [
            'outlet_id' => $this->outlet->id,
            'name' => 'Kitchen',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/v1/departments', $payload);

        $response->assertStatus(201)
                 ->assertJsonStructure(['message', 'department']);

        // Assert Department Created
        $this->assertDatabaseHas('departments', [
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'name' => 'Kitchen',
            'slug' => 'kitchen'
        ]);

        $department = Department::where('slug', 'kitchen')->where('hotel_id', $this->hotel->id)->first();
        
        // Assert Permissions Attached to Department (using intermediate table `department_permissions`)
        $this->assertDatabaseHas('department_permissions', [
            'department_id' => $department->id,
            'hotel_id' => $this->hotel->id
        ]);
    }

    #[Test]
    public function test_can_list_departments_isolated_by_tenant()
    {
        // Department for Hotel 1
        Department::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'name' => 'Front Desk',
            'slug' => 'front-desk',
        ]);

        // Create Hotel 2 and Department 2
        $hotel2 = Hotel::create(['name' => 'Other Hotel', 'domain' => 'other']);
        Department::create([
            'hotel_id' => $hotel2->id,
            'name' => 'Other Dept',
            'slug' => 'other-dept',
        ]);

        $response = $this->getJson('/api/v1/departments');

        $response->assertStatus(200)
                 ->assertJsonCount(1)
                 ->assertJsonFragment(['name' => 'Front Desk'])
                 ->assertJsonMissing(['name' => 'Other Dept']);
    }

    #[Test]
    public function test_can_update_department()
    {
        $department = Department::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'name' => 'Old Name',
            'slug' => 'old-name',
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v1/departments/{$department->id}", [
            'name' => 'New Name',
            'is_active' => false,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'New Name',
            'slug' => 'new-name',
            'is_active' => false,
        ]);
    }
}
