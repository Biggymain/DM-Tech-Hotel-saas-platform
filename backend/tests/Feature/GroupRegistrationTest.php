<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\HotelGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class GroupRegistrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_group_registration_creates_group_branch_and_admin_successfully(): void
    {
        $payload = [
            'group_name' => 'DM Tech Hotels Group',
            'hotel_name' => 'Royal Spring Hotel',
            'owner_name' => 'Micky Doe',
            'email'      => 'micky@dmtech.local',
            'password'   => 'Password123!',
            'password_confirmation' => 'Password123!',
        ];

        $response = $this->postJson('/api/v1/auth/register-group', $payload);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'group',
                     'branch',
                     'user',
                     'token'
                 ]);

        // Verify HotelGroup created
        $this->assertDatabaseHas('hotel_groups', [
            'name' => 'DM Tech Hotels Group',
            'contact_email' => 'micky@dmtech.local'
        ]);

        $group = HotelGroup::where('name', 'DM Tech Hotels Group')->first();

        // Verify Hotel created
        $this->assertDatabaseHas('hotels', [
            'name' => 'Royal Spring Hotel',
            'hotel_group_id' => $group->id
        ]);

        $branch = Hotel::where('name', 'Royal Spring Hotel')->first();

        // Verify User formed and attached to group
        $this->assertDatabaseHas('users', [
            'email' => 'micky@dmtech.local',
            'hotel_group_id' => $group->id,
            'hotel_id' => null,
            'is_super_admin' => false
        ]);

        $user = User::where('email', 'micky@dmtech.local')->first();

        // Verify Role Assignment
        $this->assertTrue($user->roles()->withoutGlobalScopes()->where('slug', 'groupadmin')->exists());

        // Verify Default Outlets
        $this->assertDatabaseHas('outlets', [
            'hotel_id' => $branch->id,
            'name' => 'Main Restaurant',
            'type' => 'restaurant'
        ]);
    }

    #[Test]
    public function test_group_registration_fails_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register-group', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['group_name', 'hotel_name', 'owner_name', 'email', 'password']);
    }
}
