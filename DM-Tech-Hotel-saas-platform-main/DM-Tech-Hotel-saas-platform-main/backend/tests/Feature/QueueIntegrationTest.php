<?php

namespace Tests\Feature;

use App\Jobs\ProcessBookingJob;
use App\Jobs\ProcessPaymentJob;
use App\Jobs\SyncToCloudJob;
use App\Models\Hotel;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Reservation;
use App\Models\RoomType;
use App\Models\User;
use App\Models\Room;
use App\Models\Guest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class QueueIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $user;
    protected $roomTypeId;
    protected $guestId;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->hotel = Hotel::factory()->create(['slug' => 'test-hotel']);
        
        $this->user = User::factory()->create([
            'hotel_id' => $this->hotel->id,
            'email' => 'staff@example.com'
        ]);

        // Fix permission schema based on migrations
        $permission = \App\Models\Permission::create([
            'hotel_id' => $this->hotel->id,
            'slug' => 'payments.process',
            'name' => 'Process Payments',
        ]);

        $role = \App\Models\Role::create([
            'hotel_id' => $this->hotel->id,
            'slug' => 'admin',
            'name' => 'Admin',
        ]);

        $role->permissions()->attach($permission->id, ['hotel_id' => $this->hotel->id]);
        $this->user->roles()->attach($role->id, ['hotel_id' => $this->hotel->id]);

        $roomType = \App\Models\RoomType::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Deluxe Room',
            'base_price' => 100.00,
            'capacity' => 2,
            'is_public' => true,
        ]);
        $this->roomTypeId = $roomType->id;

        // Add an available room
        \App\Models\Room::create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $this->roomTypeId,
            'room_number' => '101',
            'status' => 'available',
        ]);

        $guest = \App\Models\Guest::create([
            'hotel_id' => $this->hotel->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ]);
        $this->guestId = $guest->id;
    }

    #[Test]
    public function test_booking_dispatches_job()
    {
        Bus::fake();

        $response = $this->postJson("/api/v1/booking/{$this->hotel->slug}/reserve", [
            'room_type_id' => $this->roomTypeId,
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'quantity' => 1,
        ]);

        $response->assertStatus(201);
        Bus::assertDispatched(ProcessBookingJob::class);
    }

    #[Test]
    public function test_confirm_payment_chains_jobs()
    {
        Bus::fake();

        $reservationId = DB::table('reservations')->insertGetId([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $this->guestId,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'status' => 'pending',
            'reservation_number' => 'RSV-001',
            'total_amount' => 200.00,
            'source' => 'website',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/booking/{$this->hotel->slug}/confirm-payment", [
            'reservation_id' => $reservationId,
            'reference' => 'PAY-123'
        ]);

        $response->assertStatus(200);
        
        Bus::assertChained([
            ProcessBookingJob::class,
            SyncToCloudJob::class,
        ]);
    }

    #[Test]
    public function test_payment_dispatches_high_priority_job()
    {
        Queue::fake();

        $invoiceId = DB::table('invoices')->insertGetId([
            'hotel_id' => $this->hotel->id,
            'invoice_number' => 'INV-001',
            'sequence_number' => 1,
            'total_amount' => 100.00,
            'subtotal' => 100.00,
            'tax_amount' => 0,
            'service_charge' => 0,
            'amount_paid' => 0,
            'status' => 'pending',
            'currency_code' => 'USD',
            'currency_symbol' => '$',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentMethodId = DB::table('payment_methods')->insertGetId([
            'hotel_id' => $this->hotel->id,
            'name' => 'Cash',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/v1/billing/payments", [
            'invoice_id' => $invoiceId,
            'amount' => 100,
            'payment_method_id' => $paymentMethodId,
        ]);

        $response->assertStatus(202);
        
        Queue::assertPushed(ProcessPaymentJob::class, function ($job) {
            return $job->queue === 'high';
        });
    }

    #[Test]
    public function test_model_change_dispatches_sync_job()
    {
        Queue::fake();

        $room = new Room();
        $room->hotel_id = $this->hotel->id;
        $room->room_type_id = $this->roomTypeId;
        $room->room_number = '103';
        $room->save();

        Queue::assertPushed(SyncToCloudJob::class, function ($job) {
            return $job->queue === 'low';
        });
    }
}
