<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Role;
use App\Models\User;
use App\Models\RoomType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixStaffAndRoomTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);

        $this->hotel = Hotel::create([
            'name' => 'Test Hotel',
            'domain' => 'test.com',
            'email' => 'test@test.com',
            'phone' => '1234567890',
            'address' => 'Test Address'
        ]);

        $this->admin = User::factory()->create([
            'hotel_id' => $this->hotel->id,
            'is_super_admin' => false,
        ]);

        $ownerRole = Role::withoutGlobalScopes()->where('slug', 'hotelowner')->first();
        $this->admin->roles()->attach($ownerRole->id, ['hotel_id' => $this->hotel->id]);
    }

    public function test_staff_onboarding_permission()
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/users', [
            'name' => 'New Staff',
            'email' => 'staff@test.com',
            'password' => 'password123',
            'role_id' => Role::withoutGlobalScopes()->where('slug', 'reception')->first()->id,
        ]);

        $response->assertStatus(201);
    }

    public function test_room_creation()
    {
        $roomType = RoomType::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Deluxe',
            'base_price' => 100,
            'capacity' => 2
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/pms/rooms', [
            'room_number' => '101',
            'room_type_id' => $roomType->id,
            'floor' => '1'
        ]);

        $response->assertStatus(201);
    }
}
