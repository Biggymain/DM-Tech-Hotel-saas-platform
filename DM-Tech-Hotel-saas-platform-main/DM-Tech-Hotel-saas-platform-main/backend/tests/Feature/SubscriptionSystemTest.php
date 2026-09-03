<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\HotelSubscription;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SubscriptionSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $plan;
    protected $hotel;
    protected $user;
    protected $subscriptionService;

    protected function setUp(): void
    {
        parent::setUp();

        // Use a factory-created plan so the test is decoupled from seeder slugs.
        $this->plan = SubscriptionPlan::factory()->create([
            'name'          => 'Professional',
            'slug'          => 'professional',
            'price'         => 99900,
            'billing_cycle' => 'monthly',
            'max_rooms'     => null,
            'max_staff'     => null,
            'features'      => ['pms', 'pos', 'analytics'],
            'is_active'     => true,
        ]);

        $this->hotel = Hotel::factory()->create();
        $this->user = User::factory()->create([
            'hotel_id'       => $this->hotel->id,
            'is_super_admin' => false,
        ]);

        $this->subscriptionService = app(SubscriptionService::class);
    }

    #[Test]
    public function test_new_hotel_can_start_trial()
    {
        $subscription = $this->subscriptionService->createSubscription($this->hotel, $this->plan);
        
        $this->assertEquals('trial', $subscription->status);
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertTrue($this->hotel->hasActiveSubscription());
    }

    #[Test]
    public function test_active_subscription_allows_admin_access()
    {
        $this->subscriptionService->createSubscription($this->hotel, $this->plan);
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/occupancy');

        $response->assertStatus(200);
    }

    #[Test]
    public function test_expired_subscription_blocks_admin_access()
    {
        $subscription = $this->subscriptionService->createSubscription($this->hotel, $this->plan);
        
        // Manually expire and suspend
        $subscription->update([
            'status' => 'suspended',
            'ends_at' => now()->subDays(1)
        ]);
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->withHeader('X-Test-Verify-Subscription', 'true')
            ->getJson('/api/v1/admin/dashboard/occupancy');

        $response->assertStatus(402)
            ->assertJson(['error' => 'Payment Required']);
    }

    #[Test]
    public function test_grace_period_allows_temporary_access()
    {
        $subscription = $this->subscriptionService->createSubscription($this->hotel, $this->plan);
        
        // Simulate payment failure
        $this->subscriptionService->handlePaymentFailure($subscription);
        
        $this->assertEquals('grace_period', $subscription->status);
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/admin/dashboard/occupancy');

        $response->assertStatus(200); // Should still work
    }

    #[Test]
    public function test_checkout_upgrades_subscription()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/admin/subscription/checkout', [
                'plan_id' => $this->plan->id,
                'gateway' => 'stripe'
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('hotel_subscriptions', [
            'hotel_id' => $this->hotel->id,
            'status' => 'active'
        ]);
    }

    #[Test]
    public function test_invoice_generation_on_payment()
    {
        $subscription = $this->subscriptionService->createSubscription($this->hotel, $this->plan);
        
        $this->subscriptionService->recordPayment(
            $subscription,
            $this->plan->price,
            'stripe',
            'TEST_REF'
        );

        $this->assertDatabaseHas('subscription_invoices', [
            'hotel_id' => $this->hotel->id,
            'amount' => $this->plan->price
        ]);
    }

    #[Test]
    public function test_platform_analytics_returns_data()
    {
        $this->subscriptionService->createSubscription($this->hotel, $this->plan);

        // OwnerAnalyticsController requires hotel_group_id on the user
        $group = \App\Models\HotelGroup::factory()->create();
        $this->hotel->update(['hotel_group_id' => $group->id]);
        $this->user->update(['hotel_group_id' => $group->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/admin/platform/analytics');

        $response->assertStatus(200)
            ->assertJsonStructure(['stats', 'hotels']);
    }
}
