<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\GuestPortalSession;
use App\Models\GuestServiceRequest;
use App\Models\User;
use App\Events\GuestCheckedOut;
use App\Jobs\CleanExpiredGuestSessionsJob;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;

class GuestPortalTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $room;
    protected $guest;
    protected $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->withoutMiddleware(\App\Http\Middleware\LogUserActivityMiddleware::class);
        $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);

        $this->hotel = Hotel::create(['name' => 'Portal Hotel']);
        
        $roomType = RoomType::create(['hotel_id' => $this->hotel->id, 'name' => 'Standard', 'base_price' => 100]);
        $this->room = Room::create(['hotel_id' => $this->hotel->id, 'room_type_id' => $roomType->id, 'room_number' => '101', 'status' => 'available']);
        
        $this->guest = Guest::create(['hotel_id' => $this->hotel->id, 'first_name' => 'John', 'last_name' => 'Doe', 'phone' => '12345']);
        
        $this->reservation = Reservation::create([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $this->guest->id,
            'reservation_number' => 'RES-TEST-123',
            'check_in_date' => now()->subDay(),
            'check_out_date' => now()->addDays(2),
            'status' => 'checked_in',
            'total_amount' => 300,
            'adults' => 2,
            'children' => 0
        ]);
        
        $this->reservation->rooms()->attach($this->room->id);
    }

    #[Test]
    public function test_guest_portal_session_creation()
    {
        $response = $this->postJson('/api/v1/guest/session/start', [
            'hotel_id' => $this->hotel->id,
            'context_type' => 'room',
            'context_id' => $this->room->id,
            'device_info' => 'iPhone 13'
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure(['session_token', 'requires_pin']);
                 
        $this->assertDatabaseHas('guest_portal_sessions', [
            'room_id' => $this->room->id,
            'reservation_id' => $this->reservation->id,
            'guest_id' => $this->guest->id,
            'is_active' => true
        ]);
    }

    #[Test]
    public function test_guest_pin_authentication()
    {
        $session = GuestPortalSession::create([
            'hotel_id' => $this->hotel->id,
            'room_id' => $this->room->id,
            'reservation_id' => $this->reservation->id,
            'session_token' => 'token_123',
            'pin_code' => 'doe',
            'expires_at' => now()->addDay(),
            'is_active' => true
        ]);

        $response = $this->postJson('/api/v1/guest/session/authenticate', [
            'session_token' => 'token_123',
            'pin' => 'doe'
        ]);

        $response->assertStatus(200);
    }

    #[Test]
    public function test_device_fingerprint_trust()
    {
        $session = GuestPortalSession::create([
            'hotel_id' => $this->hotel->id,
            'room_id' => $this->room->id,
            'reservation_id' => $this->reservation->id,
            'session_token' => 'token_123',
            'pin_code' => 'doe',
            'expires_at' => now()->addDay(),
            'is_active' => true
        ]);

        $response = $this->postJson('/api/v1/guest/session/authenticate', [
            'session_token' => 'token_123',
            'pin' => 'doe',
            'device_fingerprint' => 'hash_abc123'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('guest_portal_sessions', [
            'session_token' => 'token_123',
            'device_fingerprint' => 'hash_abc123',
            'trusted_device' => true
        ]);
    }

    #[Test]
    public function test_trusted_device_skips_pin()
    {
        $session = GuestPortalSession::create([
            'hotel_id' => $this->hotel->id,
            'room_id' => $this->room->id,
            'reservation_id' => $this->reservation->id,
            'session_token' => 'token_123',
            'pin_code' => 'doe',
            'device_fingerprint' => 'hash_abc123',
            'trusted_device' => true,
            'expires_at' => now()->addDay(),
            'is_active' => true
        ]);

        // Deliberately wrong/null PIN, but valid fingerprint
        $response = $this->postJson('/api/v1/guest/session/authenticate', [
            'session_token' => 'token_123',
            'pin' => 'wrong',
            'device_fingerprint' => 'hash_abc123'
        ]);

        $response->assertStatus(200);
    }

    #[Test]
    public function test_guest_dashboard_returns_reservation()
    {
        $session = GuestPortalSession::create([
            'hotel_id' => $this->hotel->id,
            'room_id' => $this->room->id,
            'reservation_id' => $this->reservation->id,
            'guest_id' => $this->guest->id,
            'session_token' => 'token_123',
            'pin_code' => 'doe',
            'trusted_device' => true, // authenticated
            'expires_at' => now()->addDay(),
            'is_active' => true
        ]);

        $response = $this->getJson("/api/v1/guest/dashboard?session_token=token_123");

        $response->assertStatus(200)
                 ->assertJsonPath('reservation.status', 'checked_in')
                 ->assertJsonPath('guest.first_name', 'John');
    }

    #[Test]
    public function test_guest_service_request_creation()
    {
        $session = GuestPortalSession::create([
            'hotel_id' => $this->hotel->id,
            'room_id' => $this->room->id,
            'reservation_id' => $this->reservation->id,
            'guest_id' => $this->guest->id,
            'session_token' => 'token_123',
            'pin_code' => 'doe',
            'trusted_device' => true,
            'expires_at' => now()->addDay(),
            'is_active' => true
        ]);

        $response = $this->postJson('/api/v1/guest/requests', [
            'session_token' => 'token_123',
            'request_type' => 'housekeeping',
            'description' => 'Please bring extra towels'
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('guest_service_requests', [
            'request_type' => 'housekeeping',
            'description' => 'Please bring extra towels',
            'room_id' => $this->room->id
        ]);
        
        // Also verify Housekeeping task got generated
        $this->assertDatabaseHas('housekeeping_tasks', [
            'room_id' => $this->room->id,
            'task_type' => 'cleaning'
        ]);
    }

    #[Test]
    public function test_maintenance_request_routes_correctly()
    {
        Event::fake([\App\Events\MaintenanceRequested::class]);

        $session = GuestPortalSession::create([
            'hotel_id' => $this->hotel->id,
            'room_id' => $this->room->id,
            'reservation_id' => $this->reservation->id,
            'guest_id' => $this->guest->id,
            'session_token' => 'token_123',
            'pin_code' => 'doe',
            'trusted_device' => true,
            'expires_at' => now()->addDay(),
            'is_active' => true
        ]);

        $response = $this->postJson('/api/v1/guest/requests', [
            'session_token' => 'token_123',
            'request_type' => 'maintenance',
            'description' => 'AC is not working'
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('maintenance_requests', [
            'room_id' => $this->room->id,
            'description' => 'AC is not working'
        ]);
    }

    #[Test]
    public function test_late_checkout_request_flow()
    {
        $session = GuestPortalSession::create([
            'hotel_id' => $this->hotel->id,
            'room_id' => $this->room->id,
            'reservation_id' => $this->reservation->id,
            'guest_id' => $this->guest->id,
            'session_token' => 'token_123',
            'pin_code' => 'doe',
            'trusted_device' => true,
            'expires_at' => now()->addDay(),
            'is_active' => true
        ]);

        $response = $this->postJson('/api/v1/guest/requests', [
            'session_token' => 'token_123',
            'request_type' => 'late_checkout',
            'description' => 'Requesting 2 PM checkout'
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('guest_service_requests', [
            'request_type' => 'late_checkout',
            'room_id' => $this->room->id
        ]);
    }

    #[Test]
    public function test_session_terminated_on_checkout()
    {
        $session = GuestPortalSession::create([
            'hotel_id' => $this->hotel->id,
            'room_id' => $this->room->id,
            'reservation_id' => $this->reservation->id,
            'session_token' => 'token_123',
            'expires_at' => now()->addDay(),
            'is_active' => true
        ]);

        event(new GuestCheckedOut($this->reservation));

        $this->assertDatabaseHas('guest_portal_sessions', [
            'id' => $session->id,
            'is_active' => false
        ]);
    }

    #[Test]
    public function test_expired_sessions_cleanup()
    {
        $session = GuestPortalSession::create([
            'hotel_id' => $this->hotel->id,
            'room_id' => $this->room->id,
            'session_token' => 'token_old',
            'expires_at' => now()->subDay(),
            'is_active' => true
        ]);

        dispatch(new CleanExpiredGuestSessionsJob());

        $this->assertDatabaseHas('guest_portal_sessions', [
            'id' => $session->id,
            'is_active' => false
        ]);
    }
}
