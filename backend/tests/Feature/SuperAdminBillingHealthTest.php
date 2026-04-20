<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Hotel;
use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\HotelSubscription;
use Illuminate\Support\Carbon;

class SuperAdminBillingHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\SubscriptionPlan::unguard();
        $this->plan = SubscriptionPlan::create([
            'name' => 'Basic',
            'slug' => 'basic',
            'price' => 10,
            'billing_cycle' => 'monthly',
            'is_active' => true,
            'features' => [],
        ]);

        $this->superAdmin = User::factory()->create([
            'role' => 'superadmin',
            'is_super_admin' => true,
        ]);
    }

    public function test_health_dashboard_active_branches()
    {
        $hotel = Hotel::factory()->create(['name' => 'Active Branch']);
        HotelSubscription::create([
            'hotel_id' => $hotel->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'current_period_end' => Carbon::instance(now()->addDays(10)),
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/super-admin/billing/health');

        $response->assertStatus(200)
                 ->assertJsonFragment(['subscription_status' => 'active', 'name' => 'Active Branch']);
    }

    public function test_health_dashboard_grace_branches()
    {
        $hotel = Hotel::factory()->create(['name' => 'Grace Branch']);
        HotelSubscription::create([
            'hotel_id' => $hotel->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'current_period_end' => Carbon::instance(now()->subHours(10)), // Within 24h grace
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/super-admin/billing/health');

        $response->assertStatus(200)
                 ->assertJsonFragment(['subscription_status' => 'grace_period', 'name' => 'Grace Branch']);
    }

    public function test_health_dashboard_suspended_branches()
    {
        $hotel = Hotel::factory()->create(['name' => 'Suspended Branch']);
        HotelSubscription::create([
            'hotel_id' => $hotel->id,
            'plan_id' => $this->plan->id,
            'status' => 'suspended',
            'current_period_end' => Carbon::instance(now()->subDays(2)),
        ]);

        $response = $this->actingAs($this->superAdmin)->getJson('/api/v1/super-admin/billing/health');

        $response->assertStatus(200)
                 ->assertJsonFragment(['subscription_status' => 'suspended', 'name' => 'Suspended Branch']);
    }
}
