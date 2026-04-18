<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\HotelGroup;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;

class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = HotelGroup::create(['name' => 'Test Group', 'slug' => 'test-group']);
        $this->user = User::create([
            'name' => 'Group Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'hotel_group_id' => $this->group->id,
        ]);

        $role = Role::create(['slug' => 'groupadmin', 'name' => 'Group Admin']);
        $this->user->roles()->attach($role->id);
    }

    #[Test]
    public function test_hotel_generates_slug_on_creation()
    {
        $hotel = Hotel::create([
            'name' => 'Grand Hotel',
            'hotel_group_id' => $this->group->id,
            'domain' => 'grand-hotel.test',
        ]);

        $this->assertEquals('grand-hotel', $hotel->slug);
    }

    #[Test]
    public function test_can_onboard_manager_for_branch()
    {
        $branch = Hotel::create([
            'name' => 'Central Branch',
            'hotel_group_id' => $this->group->id,
            'domain' => 'central.test',
        ]);

        // Creating manager role
        Role::create(['slug' => 'generalmanager', 'name' => 'Manager']);

        $this->actingAs($this->user);

        $response = $this->postJson("/api/v1/organization/branches/{$branch->id}/onboard");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'manager_email',
                'temporary_password',
                'branch_slug'
            ]);

        $this->assertEquals('central-branch', $response->json('branch_slug'));
        
        $this->assertDatabaseHas('users', [
            'email' => $response->json('manager_email'),
            'hotel_id' => $branch->id
        ]);
    }
}
