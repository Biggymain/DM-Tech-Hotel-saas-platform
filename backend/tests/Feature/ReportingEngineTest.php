<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Hotel;
use App\Models\User;
use App\Models\Role;
use App\Models\Outlet;
use App\Models\Department;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Laravel\Sanctum\Sanctum;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class ReportingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $adminUser;
    protected $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = Hotel::create([
            'name' => 'BI Test Hotel',
            'domain' => 'bi-test',
            'is_active' => true,
        ]);

        $this->adminUser = User::create([
            'name' => 'Reporting Admin',
            'email' => 'admin@reports.com',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'hotel_id' => $this->hotel->id
        ]);
        
        $role = Role::create([
            'name' => 'BIAdmin',
            'slug' => 'biadmin',
            'hotel_id' => $this->hotel->id
        ]);
        
        $permissions = [
            'reports.view',
            'reports.export',
        ];

        foreach ($permissions as $permName) {
            $perm = \App\Models\Permission::create([
                'name' => ucfirst(str_replace('.', ' ', $permName)),
                'slug' => $permName,
                'module' => 'Reports',
                'hotel_id' => $this->hotel->id
            ]);
            $role->permissions()->attach($perm->id, ['hotel_id' => $this->hotel->id]);
        }
        
        $this->adminUser->roles()->attach($role->id, ['hotel_id' => $this->hotel->id]);

        app(\App\Services\PermissionService::class)->clearPermissionCache($this->adminUser);
        $this->adminUser = User::with('roles.permissions')->find($this->adminUser->id);
        $this->actingAs($this->adminUser);

        $this->outlet = Outlet::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Lobby Bar',
            'type' => 'bar'
        ]);
    }

    #[Test]
    public function test_dashboard_aggregates_today_revenue()
    {
        $department = Department::create(['hotel_id' => $this->hotel->id, 'name' => 'Bar', 'slug' => 'bar']);
        $cat = MenuCategory::create(['hotel_id' => $this->hotel->id, 'name' => 'Drinks']);
        $item = MenuItem::create([
            'hotel_id' => $this->hotel->id, 
            'outlet_id' => $this->outlet->id, 
            'department_id' => $department->id, 
            'menu_category_id' => $cat->id, 
            'name' => 'Martini', 
            'price' => 20
        ]);

        $order = Order::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'department_id' => $department->id,
            'order_number' => 'ORD-001',
            'order_source' => 'pos',
            'total_amount' => 45.00,
            'status' => 'served',
            'created_at' => Carbon::now()
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'menu_item_id' => $item->id,
            'quantity' => 2,
            'price' => 20
        ]);

        Invoice::create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'order_id' => $order->id,
            'invoice_number' => 'INV-001',
            'sequence_number' => 1,
            'subtotal' => 40,
            'tax_amount' => 4,
            'service_charge' => 1,
            'total_amount' => 45.00, // Matches today's sum
            'amount_paid' => 45.00,
            'status' => 'paid',
            'created_at' => Carbon::now()
        ]);

        $response = $this->getJson('/api/v1/reports/dashboard-summary');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'today_revenue' => 45,
                    'today_orders' => 1,
                    'top_selling_item' => 'Martini'
                ]
            ]);
    }

    #[Test]
    public function test_exports_daily_sales_csv()
    {
        $response = $this->get('/api/v1/reports/export/daily-sales');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
            // ->assertHeader('Content-Disposition', 'attachment; filename=daily_sales.csv');
    }

    #[Test]
    public function test_api_tenant_isolation_prevents_fetching_external_data()
    {
        $hotel2 = Hotel::create(['name' => 'Foreign Hotel', 'domain' => 'foreign', 'is_active' => true]);
        $outlet2 = Outlet::create(['hotel_id' => $hotel2->id, 'name' => 'Foreign Outlet', 'type' => 'restaurant']);
        $dept2 = Department::create(['hotel_id' => $hotel2->id, 'outlet_id' => $outlet2->id, 'name' => 'Foreign Dept', 'slug' => 'foreign-dept']);
        $order2 = Order::create([
            'hotel_id' => $hotel2->id,
            'outlet_id' => $outlet2->id,
            'department_id' => $dept2->id,
            'order_number' => 'ORD-FOREIGN',
            'order_source' => 'pos',
            'status' => 'served',
            'total_amount' => 1000.00
        ]);
        
        Invoice::create([
            'hotel_id' => $hotel2->id,
            'outlet_id' => $outlet2->id,
            'order_id' => $order2->id,
            'invoice_number' => 'INV-H2-999',
            'sequence_number' => 1,
            'total_amount' => 1000.00,
            'amount_paid' => 1000.00,
            'status' => 'paid',
            'created_at' => Carbon::today()
        ]);

        $response = $this->getJson('/api/v1/reports/daily-sales');
        
        $response->assertStatus(200);
        // H1 should have no records since the 1000.00 belongs to H2
        $this->assertEmpty($response->json('data'));
    }
}
