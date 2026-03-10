<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\Room;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Permission;
use App\Models\Role;
use Carbon\Carbon;

class PmsReservationTest extends TestCase
{
    use RefreshDatabase;

    protected $manager;
    protected $hotel;
    protected $roomType;
    protected $room;
    protected $guest;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        
        $this->hotel = Hotel::factory()->create();
        
        $role = Role::where('name', 'Manager')->first();
        
        $this->manager = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $this->manager->roles()->attach($role);

        $this->roomType = RoomType::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Deluxe Suite',
            'base_price' => 150.00,
            'capacity' => 2
        ]);

        $this->room = Room::create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => '101'
        ]);

        $this->guest = Guest::create([
            'hotel_id' => $this->hotel->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com'
        ]);
    }

    public function test_can_create_reservation_with_automatic_folio()
    {
        $payload = [
            'guest_id' => $this->guest->id,
            'check_in_date' => Carbon::now()->addDays(2)->toDateString(),
            'check_out_date' => Carbon::now()->addDays(5)->toDateString(),
            'rooms' => [
                ['id' => $this->room->id]
            ]
        ];

        $response = $this->actingAs($this->manager)->postJson('/api/v1/pms/reservations', $payload);

        $response->assertStatus(201)
                 ->assertJsonStructure(['data' => ['reservation_number']]);

        $this->assertDatabaseHas('reservations', [
            'hotel_id' => $this->hotel->id,
            'guest_id' => $this->guest->id,
            'total_amount' => 450.00 // 3 nights @ $150
        ]);
        
        $this->assertDatabaseHas('reservation_rooms', [
            'room_id' => $this->room->id
        ]);
    }

    public function test_prevents_overlapping_reservations_in_availability()
    {
        // Add existing booking
        $reservation = Reservation::create([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $this->guest->id,
            'reservation_number' => 'RSV-001',
            'check_in_date' => Carbon::now()->addDays(2),
            'check_out_date' => Carbon::now()->addDays(5),
            'total_amount' => 450,
            'status' => 'confirmed'
        ]);
        
        $reservation->rooms()->attach($this->room->id, ['rate' => 150]);

        $response = $this->actingAs($this->manager)->getJson('/api/v1/pms/availability?check_in_date=' . Carbon::now()->addDays(3)->toDateString() . '&check_out_date=' . Carbon::now()->addDays(6)->toDateString());

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data')); // Room 101 should not be in the list
    }
    
    public function test_check_in_opens_folio_and_sets_room_occupied()
    {
        $reservation = Reservation::create([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $this->guest->id,
            'reservation_number' => 'RSV-002',
            'check_in_date' => Carbon::today(),
            'check_out_date' => Carbon::now()->addDays(2),
            'total_amount' => 300,
            'status' => 'confirmed' // MUST be confirmed to check-in
        ]);
        $reservation->rooms()->attach($this->room->id, ['rate' => 150]);

        $response = $this->actingAs($this->manager)->postJson("/api/v1/pms/reservations/{$reservation->id}/check-in");

        $response->assertStatus(200);

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'checked_in']);
        $this->assertDatabaseHas('rooms', ['id' => $this->room->id, 'status' => 'occupied']);
        $this->assertDatabaseHas('folios', ['reservation_id' => $reservation->id, 'status' => 'open']);
    }

    public function test_check_out_requires_zero_balance_folio()
    {
        $reservation = Reservation::create([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $this->guest->id,
            'reservation_number' => 'RSV-003',
            'check_in_date' => Carbon::now()->subDays(2),
            'check_out_date' => Carbon::today(),
            'total_amount' => 300,
            'status' => 'checked_in'
        ]);
        $reservation->rooms()->attach($this->room->id, ['rate' => 150]);
        
        $folio = \App\Models\Folio::create([
            'hotel_id' => $this->hotel->id,
            'reservation_id' => $reservation->id,
            'currency' => 'USD',
            'total_charges' => 300, // Non-zero balance
            'total_payments' => 0,
            'balance' => 300
        ]);

        $response = $this->actingAs($this->manager)->postJson("/api/v1/pms/reservations/{$reservation->id}/check-out");

        $response->assertStatus(422); // Validation error blocks checkout
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'checked_in']); // Remains checked in

        // Add payment to clear balance
        \App\Models\FolioItem::create(['folio_id' => $folio->id, 'amount' => 300, 'description' => 'Cash', 'is_charge' => false]);
        $folio->update(['total_payments' => 300, 'balance' => 0]);

        $response2 = $this->actingAs($this->manager)->postJson("/api/v1/pms/reservations/{$reservation->id}/check-out");
        $response2->assertStatus(200);

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'checked_out']);
        $this->assertDatabaseHas('rooms', ['id' => $this->room->id, 'status' => 'available', 'housekeeping_status' => 'dirty']);
    }
}
