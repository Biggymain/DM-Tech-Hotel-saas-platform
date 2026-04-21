<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\Folio;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AutoCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hotel = Hotel::factory()->create(['domain' => 'test-' . uniqid() . '.com']);
    }

    #[Test]
    public function test_auto_checkout_completes_for_zero_balance()
    {
        $guest = Guest::factory()->create(['hotel_id' => $this->hotel->id]);
        $reservation = Reservation::factory()->create([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $guest->id,
            'status' => 'checked_in',
            'check_out_date' => now()->toDateString()
        ]);

        Folio::factory()->create([
            'hotel_id' => $this->hotel->id,
            'reservation_id' => $reservation->id,
            'balance' => 0
        ]);

        $this->artisan('reservations:auto-checkout')
            ->expectsOutput("Auto-checked out Reservation #{$reservation->id}")
            ->assertExitCode(0);

        $this->assertEquals('checked_out', $reservation->refresh()->status);
    }

    #[Test]
    public function test_auto_checkout_skips_guests_with_balance()
    {
        $guest = Guest::factory()->create(['hotel_id' => $this->hotel->id]);
        $reservation = Reservation::factory()->create([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $guest->id,
            'status' => 'checked_in',
            'check_out_date' => now()->toDateString()
        ]);

        Folio::factory()->create([
            'hotel_id' => $this->hotel->id,
            'reservation_id' => $reservation->id,
            'balance' => 500.00
        ]);

        $this->artisan('reservations:auto-checkout')
            ->expectsOutput("Reservation #{$reservation->id} has outstanding balance: 500. Skipping auto-checkout.")
            ->assertExitCode(0);

        $this->assertEquals('checked_in', $reservation->refresh()->status);

        // Verify Severity 10 Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'entity_id' => $reservation->id,
            'change_type' => 'auto_checkout_blocked',
            'severity_score' => 10
        ]);
    }
}
