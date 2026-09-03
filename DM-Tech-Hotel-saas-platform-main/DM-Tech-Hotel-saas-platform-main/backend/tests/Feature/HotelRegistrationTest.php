<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Hotel;
use App\Models\User;
use App\Models\Role;
use App\Models\Outlet;
use PHPUnit\Framework\Attributes\Test;

class HotelRegistrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_hotel_registration_creates_tenant_and_owner_successfully(): void
    {
        // Ensure HotelOwner role exists (normally seeded, but good to ensure for test isolation)
        Role::unguard();
        Role::create(['id' => 2, 'name' => 'HotelOwner', 'slug' => 'hotelowner']);

        $payload = [
            'hotel_name' => 'The Grand Resort',
            'owner_name' => 'John Doe',
            'email' => 'john.doe@grandresort.local',
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/v1/auth/register-hotel', $payload);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'hotel',
                     'user',
                     'token'
                 ]);

        // Verify Hotel created
        $this->assertDatabaseHas('hotels', [
            'name' => 'The Grand Resort',
            'domain' => 'the-grand-resort'
        ]);
        
        $hotel = Hotel::where('domain', 'the-grand-resort')->first();

        // Verify User formed and attached
        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@grandresort.local',
            'hotel_id' => $hotel->id,
            'is_super_admin' => false
        ]);

        $user = User::where('email', 'john.doe@grandresort.local')->first();

        // Verify Role Assignment
        $this->assertTrue($user->roles()->where('slug', 'hotelowner')->exists());

        // Verify Default Settings
        $this->assertDatabaseHas('hotel_settings', [
            'hotel_id' => $hotel->id,
            'setting_key' => 'currency',
            'setting_value' => 'USD'
        ]);

        // Verify Default Outlets
        $this->assertDatabaseHas('outlets', [
            'hotel_id' => $hotel->id,
            'name' => 'Main Restaurant',
            'type' => 'restaurant'
        ]);
        $this->assertDatabaseHas('outlets', [
            'hotel_id' => $hotel->id,
            'name' => 'Main Bar',
            'type' => 'bar'
        ]);
    }
}
