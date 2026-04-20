<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\HotelGroup;
use App\Models\Hotel;
use App\Models\SubscriptionTier;
use App\Models\User;
use App\Models\SystemSetting;
use App\Services\SubscriptionService;
use App\Services\PermissionService;
use Database\Seeders\SubscriptionTierSeeder;

class SubscriptionBillingAndGatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubscriptionTierSeeder::class);

        // Mock PermissionService so role.verify middleware passes through to controller logic
        $this->mock(PermissionService::class, function ($mock) {
            $mock->shouldReceive('hasPermission')->andReturn(true);
        });
    }

    public function test_scenario_v_mixed_tiers_with_10_percent_discount()
    {
        SystemSetting::setSetting('multi_branch_discount_rate', 0.10, 'float');
        SystemSetting::setSetting('group_licensing_fee', 1000.00, 'float');

        $group = HotelGroup::factory()->create([
            'is_licensed' => true,
        ]);

        $tier4 = SubscriptionTier::where('name', 'Business')->first(); 
        $tier2 = SubscriptionTier::where('name', 'Hospitality Lite')->first(); 

        $branch1 = Hotel::factory()->create([
            'hotel_group_id' => $group->id,
            'subscription_tier_id' => $tier4->id,
        ]);

        $branch2 = Hotel::factory()->create([
            'hotel_group_id' => $group->id,
            'subscription_tier_id' => $tier2->id,
        ]);

        $service = app(SubscriptionService::class);
        $total = $service->calculateDynamicRate($group->id);

        // Expected: (350 + 100) = 450. 450 * 0.90 = 405.
        $this->assertEquals(405.00, $total);
    }

    public function test_scenario_w_dynamic_upgrade_room_limit_enforcement()
    {
        $group = HotelGroup::factory()->create();
        
        $tier3 = SubscriptionTier::where('name', 'Boutique')->first(); // limit 15
        
        $branch = Hotel::factory()->create([
            'hotel_group_id' => $group->id,
            'subscription_tier_id' => $tier3->id,
        ]);

        $admin = User::factory()->create([
            'hotel_id' => $branch->id,
            'role' => 'admin',
        ]);

        // Mock 15 rooms
        for ($i = 1; $i <= 15; $i++) {
            \App\Models\Room::factory()->create([
                'hotel_id' => $branch->id,
                'room_number' => "10$i",
            ]);
        }

        // Attempt 16th room
        $roomType = \App\Models\RoomType::factory()->create(['hotel_id' => $branch->id]);

        $response = $this->actingAs($admin)->postJson('/api/v1/pms/rooms', [
            'room_number' => '1016',
            'room_type_id' => $roomType->id,
            'floor' => '1',
        ]);

        $response->assertStatus(403)
                 ->assertJsonFragment(['message' => 'Upgrade Required: Room limit reached for current tier.']);

        // Upgrade to Tier 4
        $tier4 = SubscriptionTier::where('name', 'Business')->first(); // limit 40
        $branch->update(['subscription_tier_id' => $tier4->id]);

        // Attempt 16th room again
        $response2 = $this->actingAs($admin)->postJson('/api/v1/pms/rooms', [
            'room_number' => '1016',
            'room_type_id' => $roomType->id,
            'floor' => '1',
        ]);

        $response2->assertStatus(201);
    }
}
