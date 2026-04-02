<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationStressTest extends TestCase
{
    use RefreshDatabase;

    protected $hotelA;
    protected $hotelB;
    protected $userA;
    protected $userB;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);

        // Setup Hotel A
        $this->hotelA = Hotel::create(['name' => 'Hotel A']);
        $this->userA = User::factory()->create(['hotel_id' => $this->hotelA->id]);
        $roleA = Role::where('name', 'GeneralManager')->first();
        if ($roleA) {
            $this->userA->roles()->attach($roleA->id);
        }

        // Setup Hotel B
        $this->hotelB = Hotel::create(['name' => 'Hotel B']);
        $this->userB = User::factory()->create(['hotel_id' => $this->hotelB->id]);
        $roleB = Role::where('name', 'GeneralManager')->first();
        if ($roleB) {
            $this->userB->roles()->attach($roleB->id);
        }

        // Ensure modules are enabled for the test hotels (Required by ModuleAccessMiddleware)
        $modules = ['pos', 'inventory-management', 'pms', 'billing', 'analytics'];
        foreach ($modules as $slug) {
            $m = \Illuminate\Support\Facades\DB::table('modules')->updateOrInsert(
                ['slug' => $slug],
                ['name' => ucfirst(str_replace('-', ' ', $slug)), 'created_at' => now(), 'updated_at' => now()]
            );
            
            $moduleId = \Illuminate\Support\Facades\DB::table('modules')->where('slug', $slug)->value('id');

            \Illuminate\Support\Facades\DB::table('hotel_modules')->insert([
                ['hotel_id' => $this->hotelA->id, 'module_id' => $moduleId, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
                ['hotel_id' => $this->hotelB->id, 'module_id' => $moduleId, 'is_enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    public function test_tenant_cannot_access_other_tenant_orders()
    {
        $outletA = Outlet::create(['hotel_id' => $this->hotelA->id, 'name' => 'Outlet A', 'type' => 'restaurant']);
        
        $departmentA = \App\Models\Department::create(['hotel_id' => $this->hotelA->id, 'name' => 'Dept', 'slug' => 'dept']);
        
        $orderA = Order::create([
            'hotel_id' => $this->hotelA->id,
            'outlet_id' => $outletA->id,
            'department_id' => $departmentA->id,
            'order_number' => 'ORD-A-01',
            'order_source' => 'pos',
            'status' => 'pending',
            'total_amount' => 100,
            'created_by' => $this->userA->id,
        ]);

        // User B tries to fetch Order A
        $response = $this->actingAs($this->userB)->getJson("/api/v1/orders/{$orderA->id}");
        
        $response->assertStatus(404); // Should be completely hidden by TenantScope

        // User B tries to list orders, should only see 0
        $listResponse = $this->actingAs($this->userB)->getJson('/api/v1/orders');
        $listResponse->assertStatus(200)->assertJsonCount(0, 'data');
    }

    public function test_tenant_cannot_access_other_tenant_inventory()
    {
        $inventoryA = InventoryItem::create([
            'hotel_id' => $this->hotelA->id,
            'sku' => 'SKU-A-01',
            'name' => 'Item A',
            'unit_of_measurement' => 'kg',
            'current_stock' => 10,
            'minimum_stock_level' => 2
        ]);

        // User B tries to fetch Inventory A
        $response = $this->actingAs($this->userB)->getJson("/api/v1/inventory/items/{$inventoryA->id}");
        $response->assertStatus(404);

        // User B tries to update Inventory A
        $updateResponse = $this->actingAs($this->userB)->putJson("/api/v1/inventory/items/{$inventoryA->id}", [
            'current_stock' => 50
        ]);
        $updateResponse->assertStatus(404);
    }

    public function test_tenant_cannot_access_other_tenant_billing()
    {
        $invoiceA = Invoice::create([
            'hotel_id' => $this->hotelA->id,
            'invoice_number' => 'INV-A-01',
            'sequence_number' => 999,
            'subtotal' => 100,
            'tax_amount' => 10,
            'total_amount' => 110,
            'status' => 'pending',
            'currency_code' => 'USD'
        ]);

        // User B tries to fetch Invoice A
        $response = $this->actingAs($this->userB)->getJson("/api/v1/billing/invoices/{$invoiceA->id}");
        $response->assertStatus(404);

        // User B tries to pay Invoice A
        $payResponse = $this->actingAs($this->userB)->postJson("/api/v1/billing/payments", [
            'invoice_id' => $invoiceA->id,
            'amount' => 110,
            'payment_method_id' => 1 // fake
        ]);
        // The validation logic might fail first because the invoice doesn't exist for User B
        $payResponse->assertStatus(422); 
    }

    public function test_tenant_cannot_access_other_tenant_reports()
    {
        // Reports are generally aggregated by hotel_id implicitly, but we verify the endpoint doesn't leak.
        \App\Models\SalesReport::create([
            'hotel_id' => $this->hotelA->id,
            'report_date' => now()->toDateString(),
            'total_revenue' => 5000,
            'total_orders' => 50,
            'currency_code' => 'USD'
        ]);

        // User B fetching dashboard summary
        $response = $this->actingAs($this->userB)->getJson("/api/v1/reports/dashboard-summary");
        
        $response->assertStatus(200);
        
        $data = $response->json('data');
        // Ensure Hotel A's data isn't leaking to Hotel B
        $this->assertEquals(0, $data['today_revenue']);
        $this->assertEquals(0, $data['today_orders']);
    }
}
