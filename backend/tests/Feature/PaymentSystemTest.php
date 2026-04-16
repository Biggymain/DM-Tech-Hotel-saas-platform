<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Hotel;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\GuestPortalSession;
use Illuminate\Support\Facades\Event;
use App\Events\PaymentInitiated;
use App\Events\PaymentCompleted;
use App\Events\PaymentRefunded;
use App\Models\RoomType;
use App\Models\Room;

class PaymentSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $guest;
    protected $reservation;
    protected $paymentGateway;
    protected $room;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->withoutMiddleware(\App\Http\Middleware\LogUserActivityMiddleware::class);
        $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);

        $this->hotel = Hotel::create(['name' => 'Grand Payment Hotel']);
        $this->guest = Guest::create(['hotel_id' => $this->hotel->id, 'first_name' => 'Jane', 'last_name' => 'Smith']);
        
        $roomType = RoomType::create(['hotel_id' => $this->hotel->id, 'name' => 'Suite', 'base_price' => 200]);
        $this->room = Room::create(['hotel_id' => $this->hotel->id, 'room_type_id' => $roomType->id, 'room_number' => '102', 'status' => 'available']);
        
        $this->reservation = Reservation::create([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $this->guest->id,
            'reservation_number' => 'RES-PAY-123',
            'check_in_date' => now()->addDay(),
            'check_out_date' => now()->addDays(3),
            'status' => 'confirmed',
            'total_amount' => 400,
            'adults' => 2,
            'children' => 0
        ]);
        
        $this->paymentGateway = PaymentGateway::create([
            'hotel_id' => $this->hotel->id,
            'gateway_name' => 'stripe',
            'api_key' => 'sk_test_123',
            'api_secret' => 'sk_secret_123',
            'is_active' => true
        ]);
        
        $user = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $this->actingAs($user);
    }

    public function test_payment_intent_creation()
    {
        Event::fake([PaymentInitiated::class]);

        $response = $this->postJson('/api/v1/payments/create-intent', [
            'amount' => 100.00,
            'currency' => 'USD',
            'gateway' => 'stripe',
            'hotel_id' => $this->hotel->id,
            'reservation_id' => $this->reservation->id
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['transaction_id', 'client_secret', 'gateway_transaction_id']);

        $this->assertDatabaseHas('payment_transactions', [
            'hotel_id' => $this->hotel->id,
            'payment_gateway' => 'stripe',
            'status' => 'authorized',
            'amount' => 100.00
        ]);
        
        Event::assertDispatched(PaymentInitiated::class);
    }

    public function test_payment_capture()
    {
        Event::fake([PaymentCompleted::class]);

        $transaction = PaymentTransaction::create([
            'hotel_id' => $this->hotel->id,
            'reservation_id' => $this->reservation->id,
            'payment_gateway' => 'stripe',
            'gateway_transaction_id' => 'pi_stripe_789',
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => 'authorized'
        ]);

        $response = $this->postJson('/api/v1/payments/confirm', [
            'transaction_id' => $transaction->id
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'captured');

        $this->assertDatabaseHas('payment_transactions', [
            'id' => $transaction->id,
            'status' => 'captured'
        ]);
        
        Event::assertDispatched(PaymentCompleted::class);
    }

    public function test_payment_refund()
    {
        Event::fake([PaymentRefunded::class]);

        $transaction = PaymentTransaction::create([
            'hotel_id' => $this->hotel->id,
            'reservation_id' => $this->reservation->id,
            'payment_gateway' => 'stripe',
            'gateway_transaction_id' => 'pi_stripe_789',
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => 'captured'
        ]);

        $response = $this->postJson('/api/v1/payments/refund', [
            'transaction_id' => $transaction->id,
            'amount' => 50.00
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'refunded');

        $this->assertDatabaseHas('payment_transactions', [
            'id' => $transaction->id,
            'status' => 'refunded'
        ]);
        
        Event::assertDispatched(PaymentRefunded::class);
    }

    public function test_transaction_context_recorded()
    {
        $response = $this->postJson('/api/v1/payments/create-intent', [
            'amount' => 100.00,
            'currency' => 'USD',
            'gateway' => 'stripe',
            'hotel_id' => $this->hotel->id,
            'reservation_id' => $this->reservation->id
        ], [
            'X-Device-Fingerprint' => 'test-fingerprint-999'
        ]);

        $response->assertStatus(201);
        
        $transactionId = $response->json('transaction_id');
        $transaction = PaymentTransaction::find($transactionId);
        
        $this->assertNotNull($transaction->context_metadata);
        $this->assertEquals('test-fingerprint-999', $transaction->context_metadata['device_fingerprint']);
        $this->assertEquals($this->reservation->id, $transaction->context_metadata['reservation_id']);
    }

    public function test_guest_portal_payment_flow()
    {
        $session = GuestPortalSession::create([
            'hotel_id' => $this->hotel->id,
            'room_id' => $this->room->id,
            'reservation_id' => $this->reservation->id,
            'guest_id' => $this->guest->id,
            'session_token' => 'portal_token_999',
            'device_fingerprint' => 'portal_fingerprint',
            'is_active' => true,
            'expires_at' => now()->addDay()
        ]);

        // When guest pays from portal, session_token is passed
        $response = $this->postJson('/api/v1/payments/create-intent', [
            'amount' => 50.00,
            'currency' => 'USD',
            'gateway' => 'stripe',
            'hotel_id' => $this->hotel->id,
            'session_token' => 'portal_token_999'
        ]);

        $response->assertStatus(201);
        $transaction = PaymentTransaction::find($response->json('transaction_id'));
        
        $this->assertEquals('portal_fingerprint', $transaction->context_metadata['device_fingerprint']);
        $this->assertEquals('guest_portal', $transaction->context_metadata['action_source']);
    }

    public function test_payment_webhook_processing()
    {
        $transaction = PaymentTransaction::create([
            'hotel_id' => $this->hotel->id,
            'payment_gateway' => 'stripe',
            'gateway_transaction_id' => 'pi_webhook_test',
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => 'authorized'
        ]);

        $response = $this->postJson('/api/v1/payments/webhook/stripe', [
            'data' => [
                'object' => [
                    'id' => 'pi_webhook_test'
                ]
            ]
        ]);

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('payment_transactions', [
            'id' => $transaction->id,
            'status' => 'captured'
        ]);
    }

    public function test_tenant_isolation_between_hotels()
    {
        $otherHotel = Hotel::create(['name' => 'Other Hotel']);
        
        // Try to create intent using another hotel's gateway (not configured)
        $response = $this->postJson('/api/v1/payments/create-intent', [
            'amount' => 100.00,
            'currency' => 'USD',
            'gateway' => 'stripe',
            'hotel_id' => $otherHotel->id
        ]);

        $response->assertStatus(403); // Forbidden because hotel_id doesn't match auth user
    }

    public function test_monnify_gateway_payment_intent()
    {
        PaymentGateway::create([
            'hotel_id' => $this->hotel->id,
            'gateway_name' => 'monnify',
            'api_key' => 'monnify_key',
            'api_secret' => 'monnify_secret',
            'contract_code' => 'monnify_contract_123',
            'payment_mode' => 'online',
            'is_active' => true
        ]);

        $response = $this->postJson('/api/v1/payments/create-intent', [
            'amount' => 150.00,
            'currency' => 'NGN',
            'gateway' => 'monnify',
            'hotel_id' => $this->hotel->id,
            'reservation_id' => $this->reservation->id
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['transaction_id', 'client_secret', 'gateway_transaction_id']);

        $this->assertDatabaseHas('payment_transactions', [
            'hotel_id' => $this->hotel->id,
            'payment_gateway' => 'monnify',
            'status' => 'authorized'
        ]);
    }

    public function test_manual_payment_confirmation()
    {
        Event::fake([PaymentCompleted::class]);

        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'Cashier', 'hotel_id' => $this->hotel->id],
            ['slug' => 'cashier']
        );
        auth()->user()->roles()->attach($role->id, ['hotel_id' => $this->hotel->id]);

        $transaction = PaymentTransaction::create([
            'hotel_id' => $this->hotel->id,
            'reservation_id' => $this->reservation->id,
            'payment_gateway' => 'pos', // Could be anything
            'gateway_transaction_id' => 'MANUAL_123',
            'amount' => 200.00,
            'currency' => 'USD',
            'status' => 'manual_pending',
            'payment_source' => 'restaurant_pos'
        ]);

        $response = $this->withPort(3003)->postJson('/api/v1/payments/manual-confirm', [
            'transaction_id' => $transaction->id
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('transaction.status', 'manual_confirmed');

        $this->assertDatabaseHas('payment_transactions', [
            'id' => $transaction->id,
            'status' => 'manual_confirmed'
        ]);

        Event::assertDispatched(PaymentCompleted::class);
    }

    public function test_payment_mode_online_only()
    {
        $this->paymentGateway->update(['payment_mode' => 'online']);

        // Attempt manual payment via online-only gateway
        $response = $this->postJson('/api/v1/payments/create-intent', [
            'amount' => 50.00,
            'currency' => 'USD',
            'gateway' => 'stripe',
            'hotel_id' => $this->hotel->id,
            'is_manual' => true
        ]);

        $response->assertStatus(500); // InvalidArgumentException
    }

    public function test_payment_mode_manual_only()
    {
        $this->paymentGateway->update(['payment_mode' => 'manual']);

        // Attempt online payment via manual-only gateway
        $response = $this->postJson('/api/v1/payments/create-intent', [
            'amount' => 50.00,
            'currency' => 'USD',
            'gateway' => 'stripe',
            'hotel_id' => $this->hotel->id,
            'is_manual' => false
        ]);

        $response->assertStatus(500); // InvalidArgumentException
    }

    public function test_duplicate_webhook_transaction_rejected()
    {
        $transaction = PaymentTransaction::create([
            'hotel_id' => $this->hotel->id,
            'payment_gateway' => 'stripe',
            'gateway_transaction_id' => 'pi_webhook_dup',
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => 'captured' // already captured
        ]);

        $response = $this->postJson('/api/v1/payments/webhook/stripe', [
            'data' => [
                'object' => [
                    'id' => 'pi_webhook_dup'
                ]
            ]
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'duplicate');
    }

    public function test_payment_source_tracking()
    {
        $response = $this->postJson('/api/v1/payments/create-intent', [
            'amount' => 100.00,
            'currency' => 'USD',
            'gateway' => 'stripe',
            'hotel_id' => $this->hotel->id,
            'is_manual' => true,
            'payment_source' => 'restaurant_pos',
            'pos_metadata' => [
                'terminal_id' => 'TERM_01',
                'receipt_number' => 'RCPT_123'
            ]
        ]);

        $response->assertStatus(201);
        
        $transactionId = $response->json('transaction_id');
        $transaction = PaymentTransaction::find($transactionId);
        
        $this->assertEquals('manual_pending', $transaction->status);
        $this->assertEquals('restaurant_pos', $transaction->payment_source);
        $this->assertEquals('TERM_01', $transaction->context_metadata['pos_metadata']['terminal_id']);
    }
}
