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
use PHPUnit\Framework\Attributes\Test;

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
        
        $role = Role::withoutGlobalScopes()->where('slug', 'manager')->first();
        
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

    #[Test]
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

    #[Test]
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
    
    #[Test]
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

    #[Test]
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

    #[Test]
    public function test_cannot_modify_reservation_after_deadline()
    {
        $this->hotel->update(['reservation_deadline_hours_before_checkin' => 24]);
        
        $reservation = Reservation::create([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $this->guest->id,
            'reservation_number' => 'RSV-004',
            'check_in_date' => Carbon::now()->addDays(2),
            'check_out_date' => Carbon::now()->addDays(5),
            'modification_deadline' => Carbon::now()->subHour(), // deadline passed
            'status' => 'confirmed'
        ]);

        $staff = User::factory()->create(['hotel_id' => $this->hotel->id]);

        $response = $this->actingAs($staff)->putJson("/api/v1/pms/reservations/{$reservation->id}", [
            'special_requests' => 'Late check-in please'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('modification_deadline');
    }

    #[Test]
    public function test_admin_can_modify_reservation_after_deadline()
    {
        $this->manager->update(['is_super_admin' => true]);

        $reservation = Reservation::create([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $this->guest->id,
            'reservation_number' => 'RSV-005',
            'check_in_date' => Carbon::now()->addDays(2),
            'check_out_date' => Carbon::now()->addDays(5),
            'modification_deadline' => Carbon::now()->subHour(), // deadline passed
            'status' => 'confirmed'
        ]);

        // Manager is admin/manager role
        $response = $this->actingAs($this->manager)->putJson("/api/v1/pms/reservations/{$reservation->id}", [
            'special_requests' => 'Urgent modification by admin'
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'special_requests' => 'Urgent modification by admin']);
    }

    #[Test]
    public function test_no_show_job_marks_reservation_and_adds_penalty()
    {
        $this->hotel->update(['no_show_penalty_type' => 'full_stay']);

        $reservation = Reservation::create([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $this->guest->id,
            'reservation_number' => 'RSV-006',
            'check_in_date' => Carbon::yesterday(), // passed check-in
            'check_out_date' => Carbon::now()->addDays(2),
            'total_amount' => 500,
            'status' => 'confirmed'
        ]);
        $reservation->rooms()->attach($this->room->id, ['rate' => 150]);

        $job = new \App\Jobs\MarkNoShowReservationsJob();
        $job->handle(app(\App\Services\FolioService::class), app(\App\Services\AuditLogService::class), app(\App\Services\ActivityLogService::class));

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'no_show']);
        
        $folio = $reservation->folios()->first();
        $this->assertNotNull($folio);

        $this->assertDatabaseHas('folio_items', [
            'folio_id' => $folio->id,
            'description' => 'No-Show Penalty',
            'amount' => 500
        ]);
    }

    #[Test]
    public function test_no_show_respects_grace_period()
    {
        $this->hotel->update(['reservation_grace_hours' => 24]);

        $reservation = Reservation::create([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $this->guest->id,
            'reservation_number' => 'RSV-007',
            'check_in_date' => Carbon::today(), // today, assuming checkin is 14:00 today. + 24 hrs > now
            'check_out_date' => Carbon::now()->addDays(2),
            'status' => 'confirmed'
        ]);

        $job = new \App\Jobs\MarkNoShowReservationsJob();
        $job->handle(app(\App\Services\FolioService::class), app(\App\Services\AuditLogService::class), app(\App\Services\ActivityLogService::class));

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed']);
    }

    #[Test]
    public function test_no_show_penalty_deposit_type()
    {
        $this->hotel->update(['no_show_penalty_type' => 'deposit']);

        $reservation = Reservation::create([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $this->guest->id,
            'reservation_number' => 'RSV-008',
            'check_in_date' => Carbon::yesterday(), // passed check-in
            'check_out_date' => Carbon::now()->addDays(2),
            'status' => 'confirmed',
            'deposit_amount' => 75.50,
            'deposit_paid' => true
        ]);

        $job = new \App\Jobs\MarkNoShowReservationsJob();
        $job->handle(app(\App\Services\FolioService::class), app(\App\Services\AuditLogService::class), app(\App\Services\ActivityLogService::class));

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'no_show']);
        
        $folio = $reservation->folios()->first();
        $this->assertNotNull($folio);

        $this->assertDatabaseHas('folio_items', [
            'folio_id' => $folio->id,
            'description' => 'No-Show Penalty',
            'amount' => 75.50
        ]);
    }

    #[Test]
    public function test_qr_session_invalidated_after_no_show()
    {
        $this->hotel->update(['no_show_penalty_type' => 'deposit']);

        $reservation = Reservation::create([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $this->guest->id,
            'reservation_number' => 'RSV-009',
            'check_in_date' => Carbon::yesterday(), // passed check-in
            'check_out_date' => Carbon::now()->addDays(2),
            'status' => 'confirmed'
        ]);

        if (!\Illuminate\Support\Facades\Schema::hasTable('room_qr_sessions')) {
            \Illuminate\Support\Facades\Schema::create('room_qr_sessions', function ($table) {
                $table->id();
                $table->foreignId('reservation_id');
                $table->boolean('is_active')->default(true);
            });
        }

        $sessionId = \Illuminate\Support\Facades\DB::table('room_qr_sessions')->insertGetId([
            'reservation_id' => $reservation->id,
            'is_active' => true
        ]);

        $job = new \App\Jobs\MarkNoShowReservationsJob();
        $job->handle(app(\App\Services\FolioService::class), app(\App\Services\AuditLogService::class), app(\App\Services\ActivityLogService::class));

        $sessionActive = \Illuminate\Support\Facades\DB::table('room_qr_sessions')->where('id', $sessionId)->value('is_active');
        $this->assertFalse((bool)$sessionActive);
    }
}
