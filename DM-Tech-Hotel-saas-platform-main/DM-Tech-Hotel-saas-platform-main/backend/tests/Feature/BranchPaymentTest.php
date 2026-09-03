<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\HotelGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_creation_requires_payment_reference()
    {
        $group = HotelGroup::factory()->create();
        $user = User::factory()->create(['hotel_group_id' => $group->id]);
        
        $role = \App\Models\Role::firstOrCreate(['slug' => 'groupadmin'], ['name' => 'Group Admin']);
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/organization/branches', [
                'name' => 'New Test Branch',
                'email' => 'test@branch.com'
            ]);

        $response->assertStatus(402);
        $response->assertJsonStructure([
            'error',
            'fee_details' => [
                'amount',
                'base_amount',
                'is_discounted'
            ]
        ]);
        
        $this->assertEquals(50.00, $response->json('fee_details.amount'));
    }

    public function test_branch_creation_with_payment_reference_succeeds()
    {
        $group = HotelGroup::factory()->create();
        $user = User::factory()->create(['hotel_group_id' => $group->id]);
        
        $role = \App\Models\Role::firstOrCreate(['slug' => 'groupadmin'], ['name' => 'Group Admin']);
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/organization/branches', [
                'name' => 'New Paid Branch',
                'email' => 'paid@branch.com',
                'payment_reference' => 'PAY-123456'
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('hotels', ['name' => 'New Paid Branch']);
    }

    public function test_discount_applied_for_existing_branches()
    {
        $group = HotelGroup::factory()->create();
        Hotel::factory()->create(['hotel_group_id' => $group->id]);
        
        $user = User::factory()->create(['hotel_group_id' => $group->id]);
        
        $role = \App\Models\Role::firstOrCreate(['slug' => 'groupadmin'], ['name' => 'Group Admin']);
        $user->roles()->attach($role->id);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/organization/branches/preflight');

        $response->assertStatus(200);
        $this->assertEquals(47.50, $response->json('fee_details.amount'));
        $this->assertTrue($response->json('fee_details.is_discounted'));
    }

    public function test_super_admin_bypasses_payment()
    {
        $group = HotelGroup::factory()->create();
        $admin = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/organization/branches', [
                'name' => 'Admin Free Branch',
                'email' => 'free@branch.com',
                'group_id' => $group->id
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('hotels', ['name' => 'Admin Free Branch']);
    }
}
