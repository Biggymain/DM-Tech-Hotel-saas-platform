<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\Room;
use App\Models\GuestPortalSession;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class GuestReservationFromPortalTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_guest_can_search_availability()
    {
        $hotel = Hotel::factory()->create();
        
        $session = GuestPortalSession::create([
            'hotel_id' => $hotel->id,
            'context_type' => 'outlet',
            'context_id' => 1,
            'session_token' => Str::random(64),
            'expires_at' => now()->addHours(24),
            'is_active' => true,
        ]);

        $roomType = RoomType::create([
            'hotel_id' => $hotel->id,
            'name' => 'Super Deluxe',
            'base_price' => 250,
            'capacity' => 2,
        ]);

        Room::create([
            'hotel_id' => $hotel->id,
            'room_number' => '501',
            'room_type_id' => $roomType->id,
            'status' => 'available'
        ]);

        $checkIn = now()->addDays(2)->format('Y-m-d');
        $checkOut = now()->addDays(4)->format('Y-m-d');

        $response = $this->withHeaders(['X-Guest-Session' => $session->session_token])
            ->postJson('/api/v1/guest/reservations/availability', [
                'check_in_date' => $checkIn,
                'check_out_date' => $checkOut,
                'adults' => 2,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['available_room_types']);
    }

    #[Test]
    public function test_guest_can_create_reservation()
    {
        $hotel = Hotel::factory()->create();
        
        $session = GuestPortalSession::create([
            'hotel_id' => $hotel->id,
            'context_type' => 'outlet',
            'context_id' => 1,
            'session_token' => Str::random(64),
            'expires_at' => now()->addHours(24),
            'is_active' => true,
        ]);

        $roomType = RoomType::create([
            'hotel_id' => $hotel->id,
            'name' => 'Super Deluxe',
            'base_price' => 250,
            'capacity' => 2,
        ]);

        $room = Room::create([
            'hotel_id' => $hotel->id,
            'room_number' => '501',
            'room_type_id' => $roomType->id,
            'status' => 'available'
        ]);

        $response = $this->withHeaders(['X-Guest-Session' => $session->session_token])
            ->postJson('/api/v1/guest/reservations', [
                'guest' => [
                    'first_name' => 'Alice',
                    'last_name' => 'Wonderland',
                    'email' => 'alice@example.com',
                ],
                'room_id' => $room->id,
                'rate' => 250,
                'check_in_date' => now()->addDays(5)->format('Y-m-d'),
                'check_out_date' => now()->addDays(7)->format('Y-m-d'),
                'adults' => 2,
                'children' => 0,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('reservation.guest.email', 'alice@example.com');

        $this->assertDatabaseHas('reservations', [
            'hotel_id' => $hotel->id,
            'source' => 'website', // Guest Portal source mapping
        ]);
        
        $this->assertDatabaseHas('guests', [
            'email' => 'alice@example.com',
        ]);
    }
}
