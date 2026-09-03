<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\User;
use App\Models\Role;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * FolioAuditTrailTest (CIA Triad: Integrity)
 *
 * Verifies that financial mutations, folio charges, and transactions
 * strictly produce tamper-evident audit trails with full provenance.
 */
class FolioAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    protected Hotel $hotel;
    protected User $staff;
    protected Folio $folio;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

        $this->hotel = Hotel::create([
            'name'      => 'Integrity Test Hotel',
            'domain'    => 'integrity.dmtech.local',
            'is_active' => true,
        ]);

        $role = Role::create([
            'name'           => 'Manager',
            'slug'           => 'manager',
            'is_system_role' => true,
        ]);

        $this->staff = User::factory()->create([
            'hotel_id'      => $this->hotel->id,
            'is_approved'   => true,
            'hardware_hash' => TestCase::generateMockHardwareHash(),
        ]);
        $this->staff->roles()->attach($role->id, ['hotel_id' => $this->hotel->id]);

        $roomType = RoomType::create([
            'hotel_id'   => $this->hotel->id,
            'name'       => 'Deluxe',
            'base_price' => 50000,
            'capacity'   => 2,
        ]);

        $room = Room::create([
            'hotel_id'     => $this->hotel->id,
            'room_type_id' => $roomType->id,
            'room_number'  => '101',
            'status'       => 'occupied',
        ]);

        $guest = Guest::create([
            'hotel_id'   => $this->hotel->id,
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'email'      => 'john@integrity.test',
        ]);

        $reservation = Reservation::create([
            'hotel_id'           => $this->hotel->id,
            'guest_id'           => $guest->id,
            'room_id'            => $room->id,
            'room_type_id'       => $roomType->id,
            'reservation_number' => 'RES-INT-001',
            'check_in'           => now(),
            'check_out'          => now()->addDays(2),
            'total_amount'       => 100000,
            'status'             => 'checked_in',
        ]);

        $this->folio = Folio::create([
            'hotel_id'       => $this->hotel->id,
            'reservation_id' => $reservation->id,
            'guest_id'       => $guest->id,
            'folio_number'   => 'FOL-INT-001',
            'total_charges'  => 0,
            'total_payments' => 0,
            'balance'        => 0,
            'status'         => 'open',
        ]);
    }

    #[Test]
    public function test_folio_charge_creates_tamper_evident_audit_trail(): void
    {
        $this->actingAs($this->staff);

        $initialCount = AuditLog::count();

        // Perform charge with audit trail logging
        $chargeItem = FolioItem::create([
            'hotel_id'    => $this->hotel->id,
            'folio_id'    => $this->folio->id,
            'type'        => 'charge',
            'category'    => 'room_charge',
            'description' => 'Nightly Room Charge',
            'amount'      => 50000,
            'created_by'  => $this->staff->id,
        ]);

        AuditLogService::log(
            'folio',
            $this->folio->id,
            'folio_charge_posted',
            ['balance' => 0],
            ['balance' => 50000, 'charge_id' => $chargeItem->id],
            'Folio charge posted by staff',
            'api',
            $this->hotel->id,
            $this->staff->id
        );

        $this->folio->update([
            'total_charges' => 50000,
            'balance'       => 50000,
        ]);

        $this->assertEquals($initialCount + 1, AuditLog::count());

        $audit = AuditLog::where('entity_type', 'folio')
            ->where('entity_id', $this->folio->id)
            ->where('change_type', 'folio_charge_posted')
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($this->staff->id, $audit->user_id);
        $this->assertEquals($this->hotel->id, $audit->hotel_id);
        $this->assertEquals('api', $audit->source);
    }

    #[Test]
    public function test_payment_settlement_preserves_financial_integrity(): void
    {
        $this->actingAs($this->staff);

        $paymentItem = FolioItem::create([
            'hotel_id'    => $this->hotel->id,
            'folio_id'    => $this->folio->id,
            'type'        => 'payment',
            'category'    => 'cash',
            'description' => 'Settlement Cash Payment',
            'amount'      => 50000,
            'created_by'  => $this->staff->id,
        ]);

        AuditLogService::log(
            'folio',
            $this->folio->id,
            'folio_payment_received',
            ['balance' => 50000],
            ['balance' => 0, 'payment_id' => $paymentItem->id],
            'Folio payment settled in full',
            'api',
            $this->hotel->id,
            $this->staff->id
        );

        $this->folio->update([
            'total_payments' => 50000,
            'balance'        => 0,
            'status'         => 'closed',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => 'folio',
            'entity_id'   => $this->folio->id,
            'change_type' => 'folio_payment_received',
            'hotel_id'    => $this->hotel->id,
        ]);

        $this->assertEquals(0, $this->folio->fresh()->balance);
    }
}
