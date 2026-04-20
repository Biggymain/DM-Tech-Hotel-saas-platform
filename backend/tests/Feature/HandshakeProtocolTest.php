<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\StaffDailyPin;
use App\Models\TableSession;
use App\Models\User;
use App\Services\TransferService;
use App\Services\RevenueService;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class HandshakeProtocolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function handshake_fails_with_invalid_pin_and_logs_security_event_on_three_failures()
    {
        $hotel = Hotel::factory()->create();
        $sourceWaitress = User::factory()->create(['hotel_id' => $hotel->id, 'is_super_admin' => true]);
        $targetWaitress = User::factory()->create(['hotel_id' => $hotel->id, 'role' => 'waiter']);

        StaffDailyPin::create([
            'hotel_id' => $hotel->id,
            'user_id' => $targetWaitress->id,
            'pin_hash' => Hash::make('1234'),
            'expires_at' => now()->addHours(8),
        ]);

        $order = Order::factory()->create(['hotel_id' => $hotel->id]);
        $item = OrderItem::create([
            'hotel_id' => $hotel->id,
            'order_id' => $order->id,
            'waiter_id' => $sourceWaitress->id,
            'quantity' => 1,
            'price' => 50,
            'subtotal' => 50,
            'status' => 'pending'
        ]);

        $cacheKey = "failed_handshakes_{$targetWaitress->id}";
        Cache::forget($cacheKey);

        // Attempt 1
        $response = $this->actingAs($sourceWaitress)->postJson('/api/v1/orders/transfer-items', [
            'item_ids' => [$item->id],
            'target_staff_id' => $targetWaitress->id,
            'target_staff_pin' => '0000',
        ]);
        $response->assertStatus(403);
        
        // Attempt 2
        $this->actingAs($sourceWaitress)->postJson('/api/v1/orders/transfer-items', [
            'item_ids' => [$item->id],
            'target_staff_id' => $targetWaitress->id,
            'target_staff_pin' => '0000',
        ]);

        // Attempt 3 - triggers SIEM severity 10 log
        $this->actingAs($sourceWaitress)->postJson('/api/v1/orders/transfer-items', [
            'item_ids' => [$item->id],
            'target_staff_id' => $targetWaitress->id,
            'target_staff_pin' => '0000',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $targetWaitress->id,
            'change_type' => 'failed_handshake'
        ]);

        // Item should still belong to source
        $this->assertEquals($sourceWaitress->id, $item->fresh()->waiter_id);
    }

    #[Test]
    public function revenue_attribution_shifts_after_successful_handshake()
    {
        $hotel = Hotel::factory()->create();
        $sourceWaitress = User::factory()->create(['hotel_id' => $hotel->id, 'is_super_admin' => true]);
        $targetWaitress = User::factory()->create(['hotel_id' => $hotel->id, 'role' => 'waiter']);

        StaffDailyPin::create([
            'hotel_id' => $hotel->id,
            'user_id' => $targetWaitress->id,
            'pin_hash' => Hash::make('1234'),
            'expires_at' => now()->addHours(8),
        ]);

        $order = Order::factory()->create(['hotel_id' => $hotel->id]);
        $item = OrderItem::create([
            'hotel_id' => $hotel->id,
            'order_id' => $order->id,
            'waiter_id' => $sourceWaitress->id,
            'quantity' => 1,
            'price' => 100,
            'subtotal' => 100,
            'status' => 'pending'
        ]);

        $response = $this->actingAs($sourceWaitress)->postJson('/api/v1/orders/transfer-items', [
            'item_ids' => [$item->id],
            'target_staff_id' => $targetWaitress->id,
            'target_staff_pin' => '1234',
        ]);

        $response->assertStatus(200);

        // Verify Liability shifted
        $this->assertEquals($targetWaitress->id, $item->fresh()->waiter_id);

        $revenueService = app(RevenueService::class);
        $performance = $revenueService->getStaffPerformance($hotel->id);

        $targetPerf = $performance->firstWhere('waiter_id', $targetWaitress->id);
        $this->assertNotNull($targetPerf);
        $this->assertEquals(100, $targetPerf->total_sales);

        $sourcePerf = $performance->firstWhere('waiter_id', $sourceWaitress->id);
        $this->assertNull($sourcePerf);
    }

    #[Test]
    public function chairman_logic_table_merge()
    {
        $hotel = Hotel::factory()->create();
        $outlet = Outlet::factory()->create(['hotel_id' => $hotel->id]);
        $chairmanWaitress = User::factory()->create(['hotel_id' => $hotel->id, 'is_super_admin' => true]);

        StaffDailyPin::create([
            'hotel_id' => $hotel->id,
            'user_id' => $chairmanWaitress->id,
            'pin_hash' => Hash::make('9999'),
            'expires_at' => now()->addHours(8),
        ]);

        // Create 3 separate table sessions and items
        $chairmanSession = TableSession::create(['hotel_id' => $hotel->id, 'outlet_id' => $outlet->id, 'table_number' => 1]);
        $order1 = Order::factory()->create(['hotel_id' => $hotel->id]);
        $item1 = OrderItem::create(['hotel_id' => $hotel->id, 'order_id' => $order1->id, 'table_session_id' => $chairmanSession->id, 'waiter_id' => $chairmanWaitress->id, 'quantity' => 1, 'price' => 10, 'subtotal' => 10]);

        $session2 = TableSession::create(['hotel_id' => $hotel->id, 'outlet_id' => $outlet->id, 'table_number' => 2]);
        $order2 = Order::factory()->create(['hotel_id' => $hotel->id]);
        $item2 = OrderItem::create(['hotel_id' => $hotel->id, 'order_id' => $order2->id, 'table_session_id' => $session2->id, 'waiter_id' => User::factory()->create()->id, 'quantity' => 1, 'price' => 20, 'subtotal' => 20]);

        $session3 = TableSession::create(['hotel_id' => $hotel->id, 'outlet_id' => $outlet->id, 'table_number' => 3]);
        $order3 = Order::factory()->create(['hotel_id' => $hotel->id]);
        $item3 = OrderItem::create(['hotel_id' => $hotel->id, 'order_id' => $order3->id, 'table_session_id' => $session3->id, 'waiter_id' => User::factory()->create()->id, 'quantity' => 1, 'price' => 30, 'subtotal' => 30]);

        // Transfer items 2 and 3 into chairman's session and liability
        $transferService = app(TransferService::class);
        $transferService->transferItems([$item2->id, $item3->id], $item2->waiter_id, $chairmanWaitress->id, '9999', $chairmanSession->id, 'Merging tables');

        $this->assertEquals($chairmanWaitress->id, $item2->fresh()->waiter_id);
        $this->assertEquals($chairmanSession->id, $item2->fresh()->table_session_id);

        // Generate Invoice for Order 1 (representing Chairman's consolidated payments)
        // Note: Realistically POS would merge order items into Order1 or generate invoice per Session. 
        // For testing we will simulate the invoice item tracking the transfer log.
        $billingService = app(BillingService::class);
        
        $item2->order_id = $order1->id;
        $item2->save();
        $item3->order_id = $order1->id;
        $item3->save();
        
        $invoice = $billingService->generateInvoiceFromOrder($order1->fresh());

        // Check if invoice items are tracking the transfer_log_id
        $this->assertEquals(3, $invoice->items->count());
        $transferredInvoiceItem = $invoice->items->firstWhere('unit_price', 20);
        $this->assertNotNull($transferredInvoiceItem->transfer_log_id);
    }
}
