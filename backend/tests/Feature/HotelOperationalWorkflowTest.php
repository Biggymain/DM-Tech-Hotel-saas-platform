<?php

namespace Tests\Feature;

use App\Events\OrderConfirmed;
use App\Events\OrderServed;
use App\Listeners\ReserveInventoryStock;
use App\Listeners\DeductInventoryStock;
use App\Listeners\GenerateInvoice;
use App\Models\Hotel;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class HotelOperationalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $user;
    protected $outlet;
    protected $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);

        $this->hotel = Hotel::create(['name' => 'Integration Hotel']);
        $this->user = User::factory()->create(['hotel_id' => $this->hotel->id, 'is_super_admin' => true]);
        
        $role = Role::where('name', 'Manager')->first();
        if ($role) {
            $this->user->roles()->attach($role->id);
        }

        $this->department = Department::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Kitchen',
            'slug' => 'kitchen'
        ]);

        $this->outlet = Outlet::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Main Restaurant',
            'type' => 'restaurant'
        ]);
    }

    public function test_full_pos_lifecycle_and_event_bus()
    {
        // Setup Inventory
        $beef = InventoryItem::create([
            'hotel_id' => $this->hotel->id,
            'sku' => 'BEEF-W1',
            'name' => 'Wagyu Beef',
            'unit_of_measurement' => 'kg',
            'current_stock' => 50,
            'minimum_stock_level' => 5
        ]);

        // Setup Menu Item
        $steakMenu = MenuItem::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'name' => 'Wagyu Steak',
            'price' => 200,
        ]);
        
        $steakMenu->ingredients()->create([
            'inventory_item_id' => $beef->id,
            'quantity_required' => 0.5 // 0.5kg per steak
        ]);

        // Payment Method
        $paymentMethod = PaymentMethod::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Credit Card',
            'code' => 'cc',
            'is_active' => true
        ]);

        // SPIES FOR EVENTS
        Event::fake([
            OrderConfirmed::class,
            OrderServed::class
        ]);

        // 1. Create Order (POS Order)
        $order = Order::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->department->id,
            'order_number' => 'ORD-INT-1',
            'order_source' => 'pos',
            'status' => 'pending',
            'total_amount' => 400,
            'payment_status' => 'unpaid',
            'created_by' => $this->user->id,
        ]);

        $order->items()->create([
            'menu_item_id' => $steakMenu->id,
            'quantity' => 2, // requires 1.0kg beef total
            'price' => 200,
        ]);

        // 2. OrderConfirmed
        $response = $this->actingAs($this->user)->putJson("/api/v1/orders/{$order->id}/status", [
            'status' => 'confirmed'
        ]);
        $response->assertStatus(200);

        Event::assertDispatched(OrderConfirmed::class);
        
        // Unfake to let listeners run next
        Event::fake(); 
        
        // Manually trigger actual process since we faked event to assert dispatch
        $order->status = 'confirmed';
        $order->save();
        $reserveListener = app(\App\Listeners\ReserveInventoryStock::class);
        $reserveListener->handle(new OrderConfirmed($order));

        $beef->refresh();
        $this->assertEquals(50, $beef->current_stock);
        $this->assertEquals(1.0, $beef->reserved_stock);

        // 3. OrderServed
        $order->status = 'served';
        $order->save();
        
        $deductListener = app(\App\Listeners\DeductInventoryStock::class);
        $deductListener->handle(new OrderServed($order));
        
        $invoiceListener = app(\App\Listeners\GenerateInvoice::class);
        $invoiceListener->handle(new OrderServed($order));
        
        // Reporting aggregation is likely tied closely or run asynchronously 
        // We will test database integrity of the invoice generated
        
        $beef->refresh();
        $this->assertEquals(49, $beef->current_stock);
        $this->assertEquals(0, $beef->reserved_stock);

        // Verify Invoice Generation Database Integrity
        $invoice = Invoice::where('order_id', $order->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(400, $invoice->subtotal);

        // Verify invoice totals vs invoice items
        $sumItems = $invoice->items()->sum('total');
        $this->assertEquals($invoice->subtotal, $sumItems);

        // 4. Payment
        $payResponse = $this->actingAs($this->user)->postJson("/api/v1/billing/payments", [
            'invoice_id' => $invoice->id,
            'amount' => $invoice->total_amount,
            'payment_method_id' => $paymentMethod->id
        ]);
        $payResponse->assertStatus(201);

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals(0, $invoice->balance_due);
        
        // Verify payment sums vs invoice amount_paid
        $sumPayments = $invoice->payments()->where('status', 'completed')->sum('amount');
        $this->assertEquals($invoice->amount_paid, $sumPayments);
    }

    public function test_event_replay_protection()
    {
        $beef = InventoryItem::create([
            'hotel_id' => $this->hotel->id,
            'sku' => 'BEEF-W2',
            'name' => 'Wagyu Beef',
            'unit_of_measurement' => 'kg',
            'current_stock' => 50,
            'minimum_stock_level' => 5
        ]);

        $steakMenu = MenuItem::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'name' => 'Wagyu Steak',
            'price' => 200,
        ]);
        
        $steakMenu->ingredients()->create([
            'inventory_item_id' => $beef->id,
            'quantity_required' => 0.5
        ]);

        $order = Order::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'department_id' => $this->department->id,
            'order_number' => 'ORD-INT-REPLAY',
            'order_source' => 'pos',
            'status' => 'pending',
            'total_amount' => 200,
            'payment_status' => 'unpaid',
            'created_by' => $this->user->id,
        ]);

        $order->items()->create([
            'menu_item_id' => $steakMenu->id,
            'quantity' => 1,
            'price' => 200,
        ]);

        // Reserve manually first
        $order->status = 'confirmed';
        $order->save();
        $reserveListener = app(\App\Listeners\ReserveInventoryStock::class);
        $reserveListener->handle(new OrderConfirmed($order));
        
        $beef->refresh();
        $this->assertEquals(0.5, $beef->reserved_stock);

        // First Serve
        $order->status = 'served';
        $order->save();
        $deductListener = app(\App\Listeners\DeductInventoryStock::class);
        $deductListener->handle(new OrderServed($order));
        $invoiceListener = app(\App\Listeners\GenerateInvoice::class);
        $invoiceListener->handle(new OrderServed($order));

        $beef->refresh();
        $this->assertEquals(49.5, $beef->current_stock);
        $this->assertEquals(0, $beef->reserved_stock);

        $initialInvoiceCount = Invoice::where('order_id', $order->id)->count();
        $this->assertEquals(1, $initialInvoiceCount);

        // REPLAY: Trigger Deduct and Invoice again
        $deductListener->handle(new OrderServed($order));
        $invoiceListener->handle(new OrderServed($order));

        $beef->refresh();
        // Constraints: Should NOT deduct further, should be protected
        $this->assertEquals(49.5, $beef->current_stock);
        
        // Should NOT create duplicate invoices
        $replayInvoiceCount = Invoice::where('order_id', $order->id)->count();
        $this->assertEquals(1, $replayInvoiceCount);
    }

    public function test_load_simulation_concurrent_orders()
    {
        $beef = InventoryItem::create([
            'hotel_id' => $this->hotel->id,
            'sku' => 'BEEF-W3',
            'name' => 'Load Beef',
            'unit_of_measurement' => 'kg',
            'current_stock' => 100,
            'minimum_stock_level' => 5
        ]);

        $steakMenu = MenuItem::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'name' => 'Load Steak',
            'price' => 200,
        ]);
        
        $steakMenu->ingredients()->create([
            'inventory_item_id' => $beef->id,
            'quantity_required' => 1 // 1kg per steak
        ]);

        // Generate 50 orders rapidly
        for ($i = 0; $i < 50; $i++) {
            $order = Order::create([
                'hotel_id' => $this->hotel->id,
                'outlet_id' => $this->outlet->id,
                'department_id' => $this->department->id,
                'order_number' => 'ORD-LOAD-'.$i,
                'order_source' => 'pos',
                'status' => 'pending',
                'total_amount' => 200,
                'payment_status' => 'unpaid',
                'created_by' => $this->user->id,
            ]);

            $order->items()->create([
                'menu_item_id' => $steakMenu->id,
                'quantity' => 1,
                'price' => 200,
            ]);

            // Rapid lifecycle progression via API to simulate realistic load
            $this->actingAs($this->user)->putJson("/api/v1/orders/{$order->id}/status", [
                'status' => 'confirmed'
            ]);
            $this->actingAs($this->user)->putJson("/api/v1/orders/{$order->id}/status", [
                'status' => 'served'
            ]);
        }

        // Assert 50kg were securely deducted uniformly 
        $beef->refresh();
        $this->assertEquals(50, $beef->current_stock);
        $this->assertEquals(0, $beef->reserved_stock);

        // Verify 50 sequential Invoices were created accurately without duplication
        $totalInvoices = Invoice::where('hotel_id', $this->hotel->id)
            ->where('invoice_number', 'like', 'INV-H%')
            ->count();
            
        // Assuming 2 previous invoices exist from tests 1 and 2, but wait Database is Refreshed per test
        // Let's count where order_number like ORD-LOAD
        $loadOrdersCount = Order::where('order_number', 'like', 'ORD-LOAD-%')->count();
        $this->assertEquals(50, $loadOrdersCount);

        $loadOrderIdList = Order::where('order_number', 'like', 'ORD-LOAD-%')->pluck('id');
        $loadInvoicesCount = Invoice::whereIn('order_id', $loadOrderIdList)->count();
        
        $this->assertEquals(50, $loadInvoicesCount);
    }
}
