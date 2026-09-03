<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Hotel;
use App\Models\HotelSetting;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\User;
use App\Models\InventoryItem;
use App\Models\LoyaltyProduct;
use App\Jobs\AwardLoyaltyPointsJob;
use App\Services\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\DB;

class EliteLodgerTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $guest;
    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->hotel = Hotel::factory()->create(['domain' => 'test-' . uniqid() . '.com']);
        $this->guest = Guest::factory()->create([
            'hotel_id' => $this->hotel->id,
            'username' => 'Micky_Admin',
            'is_onboarded' => true,
            'loyalty_points' => 0
        ]);
        $this->admin = User::factory()->create(['hotel_id' => $this->hotel->id]);

        HotelSetting::create([
            'hotel_id' => $this->hotel->id,
            'setting_key' => 'loyalty_conversion_rate',
            'setting_value' => '5000',
            'type' => 'integer'
        ]);
    }

    #[Test]
    public function test_awards_points_based_on_truncated_conversion_rate()
    {
        // ₦7,500 at ₦5,000 rate should yield 1 point
        $outlet = Outlet::factory()->create(['hotel_id' => $this->hotel->id]);
        $order = Order::factory()->create(['hotel_id' => $this->hotel->id, 'guest_id' => $this->guest->id, 'outlet_id' => $outlet->id]);
        $invoice = Invoice::factory()->create(['hotel_id' => $this->hotel->id, 'order_id' => $order->id, 'outlet_id' => $outlet->id, 'total_amount' => 7500]);
        $payment = Payment::factory()->create([
            'hotel_id' => $this->hotel->id,
            'invoice_id' => $invoice->id,
            'amount' => 7500,
            'status' => 'completed'
        ]);

        (new AwardLoyaltyPointsJob($payment->id))->handle();

        $this->guest->refresh();
        $this->assertEquals(1, $this->guest->loyalty_points);
        $this->assertDatabaseHas('loyalty_transactions', [
            'guest_id' => $this->guest->id,
            'type' => 'earn',
            'points' => 1,
            'outlet_id' => $outlet->id
        ]);
    }

    #[Test]
    public function test_awards_large_points_correctly()
    {
        // ₦500,000 at ₦5,000 rate should yield 100 points
        $outlet = Outlet::factory()->create(['hotel_id' => $this->hotel->id]);
        $order = Order::factory()->create(['hotel_id' => $this->hotel->id, 'guest_id' => $this->guest->id, 'outlet_id' => $outlet->id]);
        $invoice = Invoice::factory()->create(['hotel_id' => $this->hotel->id, 'order_id' => $order->id, 'outlet_id' => $outlet->id, 'total_amount' => 500000]);
        $payment = Payment::factory()->create([
            'hotel_id' => $this->hotel->id,
            'invoice_id' => $invoice->id,
            'amount' => 500000,
            'status' => 'completed'
        ]);

        (new AwardLoyaltyPointsJob($payment->id))->handle();

        $this->guest->refresh();
        $this->assertEquals(100, $this->guest->loyalty_points);
    }

    #[Test]
    public function test_finds_guest_by_blind_index()
    {
        $secret = config('app.key');
        $expectedHash = hash_hmac('sha256', 'Micky_Admin', $secret);
        
        $this->assertEquals($expectedHash, $this->guest->username_bindex);

        $foundGuest = Guest::where('username_bindex', $expectedHash)->first();
        $this->assertNotNull($foundGuest);
        $this->assertEquals($this->guest->id, $foundGuest->id);
    }

    #[Test]
    public function test_enforces_500_point_30_day_manual_cap()
    {
        $service = new LoyaltyService();

        // Add 400 points - OK
        $service->addPoints($this->guest, 400, 'Good guest', $this->admin->id);
        $this->assertEquals(400, $this->guest->refresh()->loyalty_points);

        // Try to add another 200 points - Should Fail
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Manual point cap exceeded");
        
        $service->addPoints($this->guest, 200, 'Very good guest', $this->admin->id);
    }

    #[Test]
    public function test_rolls_back_points_on_inventory_failure()
    {
        $this->guest->update(['loyalty_points' => 100]);
        
        $item = InventoryItem::factory()->create([
            'hotel_id' => $this->hotel->id,
            'current_stock' => 0, // Out of stock
            'name' => 'Water Bottle'
        ]);

        $product = LoyaltyProduct::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Water Bottle',
            'type' => 'item',
            'point_cost' => 50,
            'inventory_item_id' => $item->id,
            'is_active' => true
        ]);

        $service = new LoyaltyService();

        try {
            $service->redeem($this->guest, $product->id);
        } catch (\Exception $e) {
            $this->assertStringContainsString("Insufficient stock", $e->getMessage());
        }

        $this->guest->refresh();
        $this->assertEquals(100, $this->guest->loyalty_points, "Points should have rolled back");
        $this->assertDatabaseMissing('loyalty_transactions', [
            'guest_id' => $this->guest->id,
            'type' => 'redeem'
        ]);
    }

    #[Test]
    public function test_late_checkout_hidden_on_back_to_back_booking()
    {
        // 1. Create a guest and a checked-in reservation
        $guest = Guest::factory()->create(['hotel_id' => $this->hotel->id, 'is_onboarded' => true]);
        $room = \App\Models\Room::factory()->create(['hotel_id' => $this->hotel->id]);
        $reservation = \App\Models\Reservation::factory()->create([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $guest->id,
            'status' => 'checked_in',
            'check_out_date' => now()->toDateString()
        ]);
        $reservation->rooms()->attach($room);

        // 2. Create a "Late Checkout" product
        LoyaltyProduct::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Premium Late Checkout',
            'type' => 'service',
            'point_cost' => 100,
            'is_active' => true
        ]);

        $service = new LoyaltyService();

        // 3. Verify it is available first
        $available = $service->getAvailableProducts($guest);
        $this->assertTrue($available->contains('name', 'Premium Late Checkout'));

        // 4. Create a back-to-back reservation (Arrival today)
        $nextGuest = Guest::factory()->create(['hotel_id' => $this->hotel->id]);
        $nextReservation = \App\Models\Reservation::factory()->create([
            'hotel_id' => $this->hotel->id,
            'guest_id' => $nextGuest->id,
            'status' => 'confirmed',
            'check_in_date' => now()->toDateString()
        ]);
        $nextReservation->rooms()->attach($room);

        // 5. Verify "Late Checkout" is now hidden
        $available = $service->getAvailableProducts($guest);
        $this->assertFalse($available->contains('name', 'Premium Late Checkout'), "Late checkout should be hidden when a room is booked for arrival.");
    }
}
