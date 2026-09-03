<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Hotel;
use App\Models\Room;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\GuestPortalSession;
use PHPUnit\Framework\Attributes\Test;

class QRContextRoutingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_can_start_session_with_room_context()
    {
        $hotel = Hotel::factory()->create();
        
        $roomType = \App\Models\RoomType::create([
            'hotel_id' => $hotel->id,
            'name' => 'Test Deluxe',
            'base_price' => 100,
            'capacity' => 2,
        ]);

        $room = Room::create([
            'hotel_id' => $hotel->id,
            'room_number' => '101',
            'room_type_id' => $roomType->id,
            'status' => 'available'
        ]);

        $guest = Guest::create([
            'hotel_id' => $hotel->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com'
        ]);

        $reservation = Reservation::create([
            'hotel_id' => $hotel->id,
            'guest_id' => $guest->id,
            'status' => 'checked_in',
            'check_in_date' => now()->subDay(),
            'check_out_date' => now()->addDay(),
            'adults' => 1,
            'children' => 0,
            'total_amount' => 100,
            'reservation_number' => 'RES-TEST-123',
        ]);

        $reservation->rooms()->attach($room->id, ['rate' => 100]);

        $payload = [
            'hotel_id' => $hotel->id,
            'context_type' => 'room',
            'context_id' => $room->id,
            'device_info' => 'iPhone 15',
        ];
        
        $payload['signature'] = app(\App\Services\QrSignatureService::class)->generateSignature([
            'hotel_id' => $hotel->id,
            'context_type' => 'room',
            'context_id' => $room->id,
        ]);

        $response = $this->postJson('/api/v1/guest/session/start', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'session_token', 'requires_pin', 'context_type'])
            ->assertJson(['context_type' => 'room', 'requires_pin' => true]);

        $this->assertDatabaseHas('guest_portal_sessions', [
            'context_type' => 'room',
            'context_id' => $room->id,
            'guest_id' => $guest->id,
        ]);
    }

    #[Test]
    public function test_can_start_session_with_outlet_context()
    {
        $hotel = Hotel::factory()->create();
        
        // Simulating an outlet QR code (e.g., lobby bar)
        $payload = [
            'hotel_id' => $hotel->id,
            'context_type' => 'outlet',
            'context_id' => 99,
            'device_info' => 'Android Tablet',
        ];
        
        $payload['signature'] = app(\App\Services\QrSignatureService::class)->generateSignature([
            'hotel_id' => $hotel->id,
            'context_type' => 'outlet',
            'context_id' => 99,
        ]);

        $response = $this->postJson('/api/v1/guest/session/start', $payload);

        $response->assertStatus(201)
            ->assertJson(['context_type' => 'outlet', 'requires_pin' => false]); // Visitors don't need PIN

        $this->assertDatabaseHas('guest_portal_sessions', [
            'context_type' => 'outlet',
            'context_id' => 99,
            'guest_id' => null, // No guest associated yet
        ]);
    }
    #[Test]
    public function test_valid_qr_signature_starts_session()
    {
        $hotel = Hotel::factory()->create();
        
        $payload = [
            'hotel_id' => $hotel->id,
            'context_type' => 'table',
            'context_id' => 5,
            'device_info' => 'iPhone',
        ];
        
        $payload['signature'] = app(\App\Services\QrSignatureService::class)->generateSignature([
            'hotel_id' => $hotel->id,
            'context_type' => 'table',
            'context_id' => 5,
        ]);

        $response = $this->postJson('/api/v1/guest/session/start', $payload);
        $response->assertStatus(201);
    }

    #[Test]
    public function test_tampered_qr_signature_returns_403()
    {
        $hotel = Hotel::factory()->create();
        
        // Original valid payload
        $payload = [
            'hotel_id' => $hotel->id,
            'context_type' => 'table',
            'context_id' => 1,
            'device_info' => 'Android Tablet',
        ];
        
        $payload['signature'] = app(\App\Services\QrSignatureService::class)->generateSignature([
            'hotel_id' => $hotel->id,
            'context_type' => 'table',
            'context_id' => 1,
        ]);

        // Tamper with the payload after signature is generated
        $payload['context_id'] = 2; // Guest tries to change table

        $response = $this->postJson('/api/v1/guest/session/start', $payload);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Invalid or tampered QR signature.');
    }

    #[Test]
    public function test_missing_qr_signature_returns_403()
    {
        $hotel = Hotel::factory()->create();

        // Payload deliberately omits the 'signature' field
        $payload = [
            'hotel_id'    => $hotel->id,
            'context_type' => 'table',
            'context_id'  => 3,
            'device_info' => 'Chrome Browser',
        ];

        $response = $this->postJson('/api/v1/guest/session/start', $payload);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Security validation failed: Missing QR signature parameters.');
    }
}
