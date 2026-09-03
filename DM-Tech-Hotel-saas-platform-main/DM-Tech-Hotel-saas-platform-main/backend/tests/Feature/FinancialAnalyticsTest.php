<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\HotelGroup;
use App\Models\HotelSubscription;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Folio;
use App\Models\FolioItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FinancialAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected $group;
    protected $bistroPlan;
    protected $hotelBasicPlan;
    protected $hotelProPlan;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Plans with Feature Mappings
        $this->bistroPlan = SubscriptionPlan::create([
            'name' => 'Bistro',
            'slug' => 'bistro',
            'features' => ['financial_analytics', 'single_outlet_only'],
            'price' => 19,
            'is_active' => true,
        ]);

        $this->hotelBasicPlan = SubscriptionPlan::create([
            'name' => 'Hotel Basic',
            'slug' => 'hotel_basic',
            'features' => ['pms_core'],
            'price' => 49,
            'is_active' => true,
        ]);

        $this->hotelProPlan = SubscriptionPlan::create([
            'name' => 'Hotel Pro',
            'slug' => 'hotel_pro',
            'features' => ['financial_analytics', 'multi_outlet_aggregation'],
            'price' => 149,
            'is_active' => true,
        ]);

        $this->group = HotelGroup::create(['name' => 'Test Organization', 'slug' => 'test-org']);
    }

    #[Test]
    public function bistro_plan_can_access_single_outlet_analytics()
    {
        $hotel = Hotel::factory()->create([
            'hotel_group_id' => $this->group->id,
            'slug' => 'peters-bistro',
            'domain' => 'test-' . uniqid() . '.com'
        ]);
        
        HotelSubscription::create([
            'hotel_id' => $hotel->id,
            'plan_id' => $this->bistroPlan->id,
            'status' => 'active',
        ]);

        $user = User::factory()->create(['hotel_id' => $hotel->id]);
        $outlet = Outlet::factory()->create(['hotel_id' => $hotel->id, 'name' => 'Bistro Main']);

        $order = Order::factory()->create(['hotel_id' => $hotel->id, 'outlet_id' => $outlet->id, 'total_amount' => 150, 'order_status' => 'served']);
        $order->items()->create([
            'menu_item_id' => null,
            'quantity' => 1,
            'price' => 150,
            'subtotal' => 150
        ]);
        app(\App\Services\BillingService::class)->generateInvoiceFromOrder($order);

        $response = $this->actingAs($user)
            ->withHeader('X-Test-Verify-Subscription', 'true')
            ->getJson('/api/v1/analytics/revenue-summary');

        // Expected success
        $response->assertStatus(200)
                 ->assertJsonPath('data.metrics.accrued_revenue', 172.5); // 150 + 15 (tax) + 7.5 (service)
    }

    #[Test]
    public function hotel_basic_plan_is_gatekept_and_requires_upgrade()
    {
        $hotel = Hotel::factory()->create([
            'hotel_group_id' => $this->group->id,
            'slug' => 'budget-inn',
            'domain' => 'test-' . uniqid() . '.com'
        ]);
        
        HotelSubscription::create([
            'hotel_id' => $hotel->id,
            'plan_id' => $this->hotelBasicPlan->id,
            'status' => 'active',
        ]);

        $user = User::factory()->create(['hotel_id' => $hotel->id]);

        $response = $this->actingAs($user)
            ->withHeader('X-Test-Verify-Subscription', 'true')
            ->getJson('/api/v1/analytics/revenue-summary');

        // Expected failure (missing feature)
        $response->assertStatus(403)
                 ->assertJsonPath('message', 'Upgrade to Pro for Financial Analytics.');
    }

    #[Test]
    public function hotel_pro_plan_can_aggregate_multiple_outlets()
    {
        $hotel = Hotel::factory()->create([
            'hotel_group_id' => $this->group->id,
            'slug' => 'grand-resort',
            'domain' => 'test-' . uniqid() . '.com'
        ]);
        
        HotelSubscription::create([
            'hotel_id' => $hotel->id,
            'plan_id' => $this->hotelProPlan->id,
            'status' => 'active',
        ]);

        $user = User::factory()->create(['hotel_id' => $hotel->id]);
        $lounge = Outlet::factory()->create(['hotel_id' => $hotel->id, 'name' => 'Lounge']);
        $pool = Outlet::factory()->create(['hotel_id' => $hotel->id, 'name' => 'Poolside']);

        $order1 = Order::factory()->create(['hotel_id' => $hotel->id, 'outlet_id' => $lounge->id, 'total_amount' => 300, 'order_status' => 'served']);
        $order1->items()->create(['menu_item_id' => null, 'quantity' => 1, 'price' => 300, 'subtotal' => 300]);
        $order2 = Order::factory()->create(['hotel_id' => $hotel->id, 'outlet_id' => $pool->id, 'total_amount' => 450, 'order_status' => 'served']);
        $order2->items()->create(['menu_item_id' => null, 'quantity' => 1, 'price' => 450, 'subtotal' => 450]);
        
        $billing = app(\App\Services\BillingService::class);
        $billing->generateInvoiceFromOrder($order1);
        $billing->generateInvoiceFromOrder($order2);

        $response = $this->actingAs($user)
            ->withHeader('X-Test-Verify-Subscription', 'true')
            ->getJson('/api/v1/analytics/revenue-summary');

        // Expected success
        $response->assertStatus(200)
                 ->assertJsonPath('data.metrics.accrued_revenue', 862.5); // (300+450) * 1.15
    }

    #[Test]
    public function accrued_vs_cash_on_hand_distinction()
    {
        $hotel = Hotel::factory()->create(['domain' => 'test-' . uniqid() . '.com']);
        $plan = SubscriptionPlan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'features' => ['financial_analytics'],
            'price' => 100,
            'is_active' => true
        ]);
        $hotel->subscription()->create([
            'plan_id' => $plan->id,
            'status' => 'active',
            'current_period_start' => now()->subDays(1),
            'current_period_end' => now()->addDays(30)
        ]);

        $user = User::factory()->create(['hotel_id' => $hotel->id]);
        $outlet = Outlet::factory()->create(['hotel_id' => $hotel->id]);

        // Create an order and its invoice
        $order = Order::factory()->create([
            'hotel_id' => $hotel->id,
            'outlet_id' => $outlet->id,
            'total_amount' => 1000,
            'order_status' => 'served'
        ]);
        $order->items()->create(['menu_item_id' => null, 'quantity' => 1, 'price' => 1000, 'subtotal' => 1000]);

        $billingService = app(\App\Services\BillingService::class);
        $invoice = $billingService->generateInvoiceFromOrder($order);

        // Case A: Unpaid (Accrued 1000, Cash 0, Net 0)
        $response = $this->actingAs($user)
            ->withHeader('X-Test-Verify-Subscription', 'true')
            ->getJson('/api/v1/analytics/revenue-summary');

        $response->assertStatus(200)
                 ->assertJsonPath('data.metrics.accrued_revenue', 1150) // 1000 + 10% tax + 5% service
                 ->assertJsonPath('data.metrics.cash_on_hand', 0)
                 ->assertJsonPath('data.metrics.net_revenue', 0);

        // Case B: Partially Paid (Accrued 1150, Cash 500, Net 0)
        $paymentMethod = \App\Models\PaymentMethod::create([
            'hotel_id' => $hotel->id,
            'name' => 'Cash',
            'is_active' => true
        ]);
        $billingService->processPayment($invoice, 500, $paymentMethod->id, $user->id);

        $response = $this->actingAs($user)
            ->withHeader('X-Test-Verify-Subscription', 'true')
            ->getJson('/api/v1/analytics/revenue-summary');

        $response->assertJsonPath('data.metrics.cash_on_hand', 500)
                 ->assertJsonPath('data.metrics.net_revenue', 0);

        // Case C: Fully Paid (Accrued 1150, Cash 1150, Net 1150)
        $billingService->processPayment($invoice, 650, $invoice->payments->first()->payment_method_id, $user->id);

        $response = $this->actingAs($user)
            ->withHeader('X-Test-Verify-Subscription', 'true')
            ->getJson('/api/v1/analytics/revenue-summary');

        $response->assertJsonPath('data.metrics.cash_on_hand', 1150)
                 ->assertJsonPath('data.metrics.net_revenue', 1150);
    }

    #[Test]
    public function cancellation_triggers_high_severity_audit_trail()
    {
        $hotel = Hotel::factory()->create(['domain' => 'test-' . uniqid() . '.com']);
        $user = User::factory()->create(['hotel_id' => $hotel->id, 'is_super_admin' => true]);
        $outlet = Outlet::factory()->create(['hotel_id' => $hotel->id]);

        $order = Order::factory()->create([
            'hotel_id' => $hotel->id,
            'outlet_id' => $outlet->id,
            'order_status' => 'pending'
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/orders/{$order->id}/void", [
                'reason' => 'Customer changed mind'
            ]);

        $response->assertStatus(200);
        $this->assertEquals('voided', $order->fresh()->order_status);
        $this->assertEquals('Customer changed mind', $order->fresh()->void_reason);

        // Verify Audit Log entry
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => get_class($order),
            'entity_id' => $order->id,
            'change_type' => 'order_voided',
            'user_id' => $user->id,
        ]);
        
        $log = \App\Models\AuditLog::where('entity_type', get_class($order))
            ->where('entity_id', $order->id)
            ->where('change_type', 'order_voided')
            ->first();
            
        $this->assertEquals('Customer changed mind', $log->reason);
    }
}
