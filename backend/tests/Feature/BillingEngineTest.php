<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Hotel;
use App\Models\Outlet;
use App\Models\Department;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Currency;
use App\Events\OrderServed;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;

class BillingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Hotel $hotel;
    protected Outlet $outlet;
    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);

        $this->currency = Currency::create([
            'code' => 'GBP',
            'name' => 'British Pound',
            'symbol' => '£',
            'decimal_places' => 2,
        ]);

        $this->hotel = Hotel::create([
            'name' => 'Billing Hotel',
            'domain' => 'billing.test',
            'email' => 'billing@hotel.com',
            'currency_id' => $this->currency->id
        ]);

        $this->outlet = Outlet::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Billing Outlet'
        ]);

        $this->adminUser = User::create([
            'name' => 'Billing Admin',
            'email' => 'admin@billing.com',
            'password' => bcrypt('password'),
            'hotel_id' => $this->hotel->id
        ]);
        
        $role = \App\Models\Role::create([
            'name' => 'FinanceAdmin',
            'slug' => 'financeadmin',
            'hotel_id' => $this->hotel->id
        ]);
        
        $permissions = [
            'billing.view',
            'billing.manage',
            'payments.process',
            'payments.refund',
        ];

        foreach ($permissions as $permName) {
            $perm = \App\Models\Permission::create([
                'name' => ucfirst(str_replace('.', ' ', $permName)),
                'slug' => $permName,
                'module' => 'Billing',
                'hotel_id' => $this->hotel->id
            ]);
            $role->permissions()->attach($perm->id, ['hotel_id' => $this->hotel->id]);
        }
        
        $this->adminUser->roles()->attach($role->id, ['hotel_id' => $this->hotel->id]);

        app(\App\Services\PermissionService::class)->clearPermissionCache($this->adminUser);
        $this->adminUser = User::with('roles.permissions')->find($this->adminUser->id);
        $this->actingAs($this->adminUser);
    }

    #[Test]
    public function test_invoice_creation_on_order_served()
    {
        $department = Department::create(['hotel_id' => $this->hotel->id, 'name' => 'Kitchen', 'slug' => 'kitchen']);

        $order = Order::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'department_id' => $department->id,
            'order_number' => 'ORD-123',
            'order_source' => 'pos',
            'status' => 'pending',
            'total_amount' => 300
        ]);

        for ($i = 0; $i < 3; $i++) {
            OrderItem::create([
                'order_id' => $order->id,
                'price' => 100, // 300 subtotal
                'quantity' => 1
            ]);
        }

        // Trigger Event manually to simulate controller flow
        event(new OrderServed($order));

        $this->assertDatabaseHas('invoices', [
            'hotel_id' => $this->hotel->id,
            'order_id' => $order->id,
            'subtotal' => 300,
            'tax_amount' => 30, // 10%
            'service_charge' => 15, // 5%
            'total_amount' => 345,
            'status' => 'pending',
            'currency_code' => 'GBP',
            'currency_symbol' => '£'
        ]);
        
        $invoice = Invoice::where('order_id', $order->id)->first();
        $this->assertCount(3, $invoice->items);
    }

    #[Test]
    public function test_payment_processing_and_partial_payments()
    {
        $invoice = Invoice::create([
            'hotel_id' => $this->hotel->id,
            'invoice_number' => 'INV-TEST-001',
            'sequence_number' => 1,
            'total_amount' => 500,
            'subtotal' => 500,
            'amount_paid' => 0,
            'status' => 'pending'
        ]);

        $method = PaymentMethod::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Credit Card',
        ]);

        // First partial payment 200
        $response = $this->actingAs($this->adminUser)->postJson("/api/v1/billing/payments", [
            'invoice_id' => $invoice->id,
            'amount' => 200,
            'payment_method_id' => $method->id,
        ]);

        $response->assertStatus(202);
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 200,
            'type' => 'payment'
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'partially_paid',
            'amount_paid' => 200
        ]);

        // Final payment 300
        $response2 = $this->actingAs($this->adminUser)->postJson("/api/v1/billing/payments", [
            'invoice_id' => $invoice->id,
            'amount' => 300,
            'payment_method_id' => $method->id,
        ]);

        $response2->assertStatus(202);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'paid',
            'amount_paid' => 500
        ]);
    }

    #[Test]
    public function test_refund_workflow()
    {
        $invoice = Invoice::create([
            'hotel_id' => $this->hotel->id,
            'invoice_number' => 'INV-TEST-002',
            'sequence_number' => 2,
            'total_amount' => 500,
            'subtotal' => 500,
            'amount_paid' => 500,
            'status' => 'paid'
        ]);

        $method = PaymentMethod::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Credit Card',
        ]);

        $payment = \App\Models\Payment::create([
            'hotel_id' => $this->hotel->id,
            'invoice_id' => $invoice->id,
            'payment_method_id' => $method->id,
            'type' => 'payment',
            'amount' => 500,
            'status' => 'completed',
        ]);

        // Refund 200 out of 500
        $response = $this->actingAs($this->adminUser)->postJson("/api/v1/billing/payments/{$payment->id}/refund", [
            'amount' => 200,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 200,
            'type' => 'refund'
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'partially_paid', // 300 paid out of 500
            'amount_paid' => 300
        ]);
    }
}
