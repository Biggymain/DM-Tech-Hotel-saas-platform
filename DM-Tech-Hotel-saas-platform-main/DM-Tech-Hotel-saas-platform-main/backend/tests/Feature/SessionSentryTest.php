<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\StaffDailyPin;
use App\Models\User;
use App\Models\GuestPortalSession;
use App\Services\BillingService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use App\Http\Middleware\VerifyGuestSession;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SessionSentryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function post_payment_lockdown_kills_session_and_starts_fresh_on_rescan()
    {
        $hotel = Hotel::factory()->create();
        $waitress = User::factory()->create(['hotel_id' => $hotel->id]);
        
        $session = GuestPortalSession::create([
            'hotel_id' => $hotel->id,
            'context_type' => 'order',
            'session_token' => 'token_123',
            'status' => 'active',
            'expires_at' => now()->addHours(1),
        ]);

        $order = Order::factory()
            ->has(\App\Models\OrderItem::factory()->state([
                'hotel_id' => $hotel->id,
                'quantity' => 1,
                'price' => 100,
                'subtotal' => 100,
                'status' => 'pending'
            ]), 'items')
            ->create(['hotel_id' => $hotel->id]);

        $session->context_id = $order->id;
        $session->save();

        $billingService = app(BillingService::class);
        $invoice = $billingService->generateInvoiceFromOrder($order->fresh('items'));
        
        $paymentMethod = \App\Models\PaymentMethod::create([
            'hotel_id' => $hotel->id,
            'name' => 'Cash',
            'is_active' => true
        ]);

        // Emulate guest request with cookie
        $this->withCookie('guest_session', 'token_123');
        $billingService->processPayment($invoice, $invoice->total_amount, $paymentMethod->id, $waitress->id);

        $this->assertEquals('revoked', $session->fresh()->status);
    }

    #[Test]
    public function handshake_double_lock_prevents_ordering_until_activated()
    {
        $hotel = Hotel::factory()->create();
        $waitress = User::factory()->create(['hotel_id' => $hotel->id, 'is_super_admin' => true]);

        StaffDailyPin::create([
            'hotel_id' => $hotel->id,
            'user_id' => $waitress->id,
            'pin_hash' => Hash::make('1234'),
            'expires_at' => now()->addHours(8),
        ]);

        $session = GuestPortalSession::create([
            'hotel_id' => $hotel->id,
            'session_token' => 'token_abc',
            'status' => 'pending_activation',
            'expires_at' => now()->addHours(1),
        ]);

        $request = Request::create('/api/v1/guest/orders', 'POST');
        $request->cookies->set('guest_session', 'token_abc');
        
        $middleware = new VerifyGuestSession();
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['message' => 'Success']);
        });

        // Assert 403 Locked
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('pending_activation', json_decode($response->getContent(), true)['status']);

        // Now activate it
        $actResponse = $this->actingAs($waitress)->postJson('/api/v1/orders/activate-session', [
            'session_token' => 'token_abc',
            'waiter_id' => $waitress->id,
            'waiter_pin' => '1234',
        ]);
        $actResponse->assertStatus(200);
        $this->assertEquals('active', $session->fresh()->status);
        
        $requestActive = Request::create('/api/v1/guest/orders', 'POST');
        $requestActive->cookies->set('guest_session', 'token_abc');
        $responseActive = $middleware->handle($requestActive, function ($req) {
            return response()->json(['message' => 'Success']);
        });

        // Assert fully unlocked
        $this->assertEquals(200, $responseActive->getStatusCode());
    }

    #[Test]
    public function manager_kill_switch_revokes_session_on_security_void()
    {
        $hotel = Hotel::factory()->create();
        $manager = User::factory()->create(['hotel_id' => $hotel->id, 'is_super_admin' => true]);

        $order = Order::factory()->create(['hotel_id' => $hotel->id]);
        
        $session = GuestPortalSession::create([
            'hotel_id' => $hotel->id,
            'context_type' => 'order',
            'context_id' => $order->id,
            'session_token' => 'token_xyz',
            'status' => 'active',
            'expires_at' => now()->addHours(1),
        ]);

        $orderService = app(OrderService::class);
        $this->withCookie('guest_session', 'token_xyz');

        $orderService->voidOrder($order, "Security check failed", $manager->id);

        $this->assertEquals('revoked', $session->fresh()->status);
    }

    #[Test]
    public function reaper_deletes_old_pending_sessions()
    {
        $hotel = Hotel::factory()->create();
        $session = GuestPortalSession::create([
            'hotel_id' => $hotel->id,
            'session_token' => 'old_pending',
            'status' => 'pending_activation',
            'expires_at' => now()->addHours(1),
        ]);
        
        // Force the last activity time to be older than 5 minutes
        \Illuminate\Support\Facades\DB::table('guest_portal_sessions')
            ->where('id', $session->id)
            ->update(['last_activity_at' => now()->subMinutes(10)]);

        $reaped = app(\App\Services\SessionSentryService::class)->reapPendingSessions();
        $this->assertEquals(1, $reaped);
        $this->assertDatabaseMissing('guest_portal_sessions', ['session_token' => 'old_pending']);
    }

    #[Test]
    public function reaper_preserves_new_pending_sessions()
    {
        $hotel = Hotel::factory()->create();
        $session = GuestPortalSession::create([
            'hotel_id' => $hotel->id,
            'session_token' => 'new_pending',
            'status' => 'pending_activation',
            'expires_at' => now()->addHours(1),
        ]);
        
        // Ensure it's young
        \Illuminate\Support\Facades\DB::table('guest_portal_sessions')
            ->where('id', $session->id)
            ->update(['created_at' => now()->subMinutes(2)]);

        $reaped = app(\App\Services\SessionSentryService::class)->reapPendingSessions();
        $this->assertEquals(0, $reaped);
        $this->assertDatabaseHas('guest_portal_sessions', ['session_token' => 'new_pending']);
    }

    #[Test]
    public function middleware_updates_last_activity_at()
    {
        $hotel = Hotel::factory()->create();
        $session = GuestPortalSession::create([
            'hotel_id' => $hotel->id,
            'session_token' => 'heartbeat_token',
            'status' => 'active',
            'last_activity_at' => now()->subMinutes(10),
            'expires_at' => now()->addHours(1),
        ]);

        $request = Request::create('/api/v1/guest/dashboard', 'GET');
        $request->cookies->set('guest_session', 'heartbeat_token');

        $middleware = new \App\Http\Middleware\VerifyGuestSession();
        $middleware->handle($request, function ($req) {
            return response()->json(['message' => 'Success']);
        });

        $this->assertNotNull($session->fresh()->last_activity_at);
        $this->assertTrue($session->fresh()->last_activity_at->gt(now()->subSeconds(5)));
    }

    #[Test]
    public function activate_session_route_alias_works()
    {
        $hotel = Hotel::factory()->create();
        $waitress = User::factory()->create(['hotel_id' => $hotel->id, 'is_super_admin' => true]);
        StaffDailyPin::create([
            'hotel_id' => $hotel->id,
            'user_id' => $waitress->id,
            'pin_hash' => Hash::make('5678'),
            'expires_at' => now()->addHours(8),
        ]);

        $session = GuestPortalSession::create([
            'hotel_id' => $hotel->id,
            'session_token' => 'alias_token',
            'status' => 'pending_activation',
            'expires_at' => now()->addHours(1),
        ]);

        $response = $this->actingAs($waitress)->postJson('/api/v1/pos/activate-session', [
            'session_token' => 'alias_token',
            'waiter_id' => $waitress->id,
            'waiter_pin' => '5678',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('active', $session->fresh()->status);
    }

    #[Test]
    public function activate_session_sets_last_activity_at()
    {
        $hotel = Hotel::factory()->create();
        $waitress = User::factory()->create(['hotel_id' => $hotel->id, 'is_super_admin' => true]);
        StaffDailyPin::create([
            'hotel_id' => $hotel->id,
            'user_id' => $waitress->id,
            'pin_hash' => Hash::make('1111'),
            'expires_at' => now()->addHours(8),
        ]);

        $session = GuestPortalSession::create([
            'hotel_id' => $hotel->id,
            'session_token' => 'touch_token',
            'status' => 'pending_activation',
            'expires_at' => now()->addHours(1),
        ]);

        $this->actingAs($waitress)->postJson('/api/v1/pos/activate-session', [
            'session_token' => 'touch_token',
            'waiter_id' => $waitress->id,
            'waiter_pin' => '1111',
        ]);

        $this->assertNotNull($session->fresh()->last_activity_at);
    }

    #[Test]
    public function revocation_on_payment_via_header_token()
    {
        $hotel = Hotel::factory()->create();
        $waitress = User::factory()->create(['hotel_id' => $hotel->id]);
        
        $session = GuestPortalSession::create([
            'hotel_id' => $hotel->id,
            'session_token' => 'header_token_pay',
            'status' => 'active',
            'expires_at' => now()->addHours(1),
        ]);

        $order = Order::factory()
            ->has(\App\Models\OrderItem::factory()->state([
                'hotel_id' => $hotel->id,
                'quantity' => 1,
                'price' => 50,
                'subtotal' => 50,
                'status' => 'pending'
            ]), 'items')
            ->create(['hotel_id' => $hotel->id]);
        
        $session->context_type = 'order';
        $session->context_id = $order->id;
        $session->save();

        $billingService = app(BillingService::class);
        $invoice = $billingService->generateInvoiceFromOrder($order->fresh('items'));
        
        $paymentMethod = \App\Models\PaymentMethod::create([
            'hotel_id' => $hotel->id,
            'name' => 'Card',
            'is_active' => true
        ]);

        $this->withHeader('X-Guest-Session', 'header_token_pay');
        $billingService->processPayment($invoice, $invoice->total_amount, $paymentMethod->id, $waitress->id);

        $this->assertEquals('revoked', $session->fresh()->status);
    }

    #[Test]
    public function revocation_on_security_void_via_header_token()
    {
        $hotel = Hotel::factory()->create();
        $manager = User::factory()->create(['hotel_id' => $hotel->id, 'is_super_admin' => true]);

        $order = Order::factory()->create(['hotel_id' => $hotel->id]);
        
        $session = GuestPortalSession::create([
            'hotel_id' => $hotel->id,
            'context_type' => 'order',
            'context_id' => $order->id,
            'session_token' => 'header_token_void',
            'status' => 'active',
            'expires_at' => now()->addHours(1),
        ]);

        $this->withHeader('X-Guest-Session', 'header_token_void');
        app(OrderService::class)->voidOrder($order, "Security Breach Detected", $manager->id);

        $this->assertEquals('revoked', $session->fresh()->status);
    }

    #[Test]
    public function unactivated_session_allows_get_requests()
    {
        $hotel = Hotel::factory()->create();
        $session = GuestPortalSession::create([
            'hotel_id' => $hotel->id,
            'session_token' => 'get_token',
            'status' => 'pending_activation',
            'expires_at' => now()->addHours(1),
        ]);

        $request = Request::create('/api/v1/guest/menu/1', 'GET');
        $request->cookies->set('guest_session', 'get_token');

        $middleware = new \App\Http\Middleware\VerifyGuestSession();
        $response = $middleware->handle($request, function ($req) {
            return response()->json(['message' => 'Success']);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }
}
