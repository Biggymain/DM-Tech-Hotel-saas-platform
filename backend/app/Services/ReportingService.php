<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\InventoryTransaction;
use App\Models\SalesReport;
use App\Models\SalesReportItem;
use App\Models\InventoryUsageReport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    /**
     * Get real-time dashboard summary stats
     */
    public function getDashboardSummary(int $hotelId): array
    {
        $today = Carbon::today();

        $invoices = Invoice::where('hotel_id', $hotelId)
            ->whereDate('created_at', $today)
            ->get();

        $todayRevenue = $invoices->sum('total_amount');
        $todayOrders = Order::where('hotel_id', $hotelId)
            ->whereDate('created_at', $today)
            ->where('status', '!=', 'cancelled')
            ->count();

        $aov = $todayOrders > 0 ? $todayRevenue / $todayOrders : 0;

        // Top selling item today
        $topItem = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->where('orders.hotel_id', $hotelId)
            ->whereDate('orders.created_at', $today)
            ->where('orders.status', '!=', 'cancelled')
            ->select('menu_items.name', DB::raw('SUM(order_items.quantity) as total_sold'))
            ->groupBy('menu_items.id', 'menu_items.name')
            ->orderByDesc('total_sold')
            ->first();

        // Low stock threshold
        $lowStockItems = \App\Models\InventoryItem::where('hotel_id', $hotelId)
            ->whereRaw('current_stock <= minimum_stock_level')
            ->select('id', 'name', 'current_stock', 'minimum_stock_level')
            ->get();

        return [
            'today_revenue' => round($todayRevenue, 2),
            'today_orders' => $todayOrders,
            'average_order_value' => round($aov, 2),
            'top_selling_item' => $topItem ? $topItem->name : null,
            'low_stock_items' => $lowStockItems
        ];
    }

    /**
     * Get revenue aggregated by date
     */
    public function getDailySales(int $hotelId, string $startDate, string $endDate): array
    {
        // Try to fetch from pre-aggregated reports first
        $cachedReports = SalesReport::where('hotel_id', $hotelId)
            ->where('report_type', 'daily')
            ->whereBetween('report_date', [$startDate, $endDate])
            ->orderBy('report_date', 'asc')
            ->get();

        if ($cachedReports->isNotEmpty()) {
            return $cachedReports->toArray();
        }

        // Real-time query if not generated yet
        $invoices = Invoice::where('hotel_id', $hotelId)
            ->whereIn('status', ['paid', 'partially_paid'])
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as total_revenue'),
                DB::raw('SUM(tax_amount) as total_tax'),
                DB::raw('SUM(service_charge) as total_service_charge'),
                DB::raw('COUNT(id) as total_invoices')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $invoices->toArray();
    }

    /**
     * Get revenue aggregated by outlet
     */
    public function getOutletPerformance(int $hotelId, string $startDate, string $endDate): array
    {
        return DB::table('invoices')
            ->join('outlets', 'invoices.outlet_id', '=', 'outlets.id')
            ->where('invoices.hotel_id', $hotelId)
            ->whereIn('invoices.status', ['paid', 'partially_paid'])
            ->whereBetween(DB::raw('DATE(invoices.created_at)'), [$startDate, $endDate])
            ->select(
                'outlets.name as outlet_name',
                DB::raw('SUM(invoices.total_amount) as total_revenue'),
                DB::raw('COUNT(invoices.id) as total_orders')
            )
            ->groupBy('outlets.id', 'outlets.name')
            ->orderByDesc('total_revenue')
            ->get()
            ->toArray();
    }

    /**
     * Get best selling menu items
     */
    public function getMenuPerformance(int $hotelId, string $startDate, string $endDate): array
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('menu_items', 'order_items.menu_item_id', '=', 'menu_items.id')
            ->leftJoin('menu_categories', 'menu_items.menu_category_id', '=', 'menu_categories.id')
            ->where('orders.hotel_id', $hotelId)
            ->where('orders.status', '!=', 'cancelled')
            ->whereBetween(DB::raw('DATE(orders.created_at)'), [$startDate, $endDate])
            ->select(
                'menu_items.name as item_name',
                'menu_categories.name as category_name',
                DB::raw('SUM(order_items.quantity) as quantity_sold'),
                DB::raw('SUM(order_items.price * order_items.quantity) as total_revenue')
            )
            ->groupBy('menu_items.id', 'menu_items.name', 'menu_categories.name')
            ->orderByDesc('quantity_sold')
            ->get()
            ->toArray();
    }

    /**
     * Get breakdown of payment methods used
     */
    public function getPaymentBreakdown(int $hotelId, string $startDate, string $endDate): array
    {
        return DB::table('payments')
            ->join('payment_methods', 'payments.payment_method_id', '=', 'payment_methods.id')
            ->where('payments.hotel_id', $hotelId)
            ->where('payments.status', 'completed')
            ->whereBetween(DB::raw('DATE(payments.created_at)'), [$startDate, $endDate])
            ->select(
                'payment_methods.name as payment_method',
                'payments.type as transaction_type',
                DB::raw('SUM(payments.amount) as total_amount'),
                DB::raw('COUNT(payments.id) as transaction_count')
            )
            ->groupBy('payment_methods.id', 'payment_methods.name', 'payments.type')
            ->get()
            ->toArray();
    }

    /**
     * Get inventory items deducted/consumed
     */
    public function getInventoryUsage(int $hotelId, string $startDate, string $endDate): array
    {
        // Check cache first
        $cachedReports = InventoryUsageReport::with('inventoryItem', 'outlet')
            ->where('hotel_id', $hotelId)
            ->whereBetween('report_date', [$startDate, $endDate])
            ->get();

        if ($cachedReports->isNotEmpty()) {
            return $cachedReports->groupBy('inventory_item_id')->map(function ($items) {
                $first = $items->first();
                return [
                    'item_name' => collect([$first->inventoryItem])->first()->name ?? 'Unknown',
                    'sku' => collect([$first->inventoryItem])->first()->sku ?? 'Unknown',
                    'quantity_used' => $items->sum('quantity_used'),
                    'cost_value' => $items->sum('cost_value')
                ];
            })->values()->toArray();
        }

        return DB::table('inventory_transactions')
            ->join('inventory_items', 'inventory_transactions.inventory_item_id', '=', 'inventory_items.id')
            ->where('inventory_transactions.hotel_id', $hotelId)
            ->where('inventory_transactions.type', 'deduction')
            ->whereBetween(DB::raw('DATE(inventory_transactions.created_at)'), [$startDate, $endDate])
            ->select(
                'inventory_items.name as item_name',
                'inventory_items.sku as sku',
                DB::raw('SUM(inventory_transactions.quantity) as quantity_used'),
                // For simplicity, aggregate cost based on current unit cost
                DB::raw('SUM(inventory_transactions.quantity * inventory_items.cost_per_unit) as cost_value')
            )
            ->groupBy('inventory_items.id', 'inventory_items.name', 'inventory_items.sku')
            ->orderByDesc('quantity_used')
            ->get()
            ->toArray();
    }

    /**
     * Generate Daily Cache Reports
     */
    public function generateNightlyReports(int $hotelId, string $date): void
    {
        DB::transaction(function () use ($hotelId, $date) {
            
            // Generate Sales Report
            $salesData = Invoice::where('hotel_id', $hotelId)
                ->whereIn('status', ['paid', 'partially_paid'])
                ->whereDate('created_at', $date)
                ->get();
            
            if ($salesData->isEmpty()) return;

            $hotel = \App\Models\Hotel::with('currency')->find($hotelId);

            $salesReport = SalesReport::updateOrCreate(
                ['hotel_id' => $hotelId, 'report_date' => $date, 'report_type' => 'daily'],
                [
                    'total_revenue' => $salesData->sum('total_amount'),
                    'total_orders' => $salesData->count(),
                    'total_tax' => $salesData->sum('tax_amount'),
                    'total_service_charge' => $salesData->sum('service_charge'),
                    'currency_code' => $hotel->currency->code ?? 'USD',
                    'currency_symbol' => $hotel->currency->symbol ?? '$',
                ]
            );

            // Generate Daily Item Sales Report
            $menuPerformance = $this->getMenuPerformance($hotelId, $date, $date);
            
            // Delete old items if re-running
            $salesReport->items()->delete();

            foreach ($menuPerformance as $item) {
                // Find actual menu item
                $menuItem = \App\Models\MenuItem::where('hotel_id', $hotelId)
                    ->where('name', $item->item_name)
                    ->first();

                SalesReportItem::create([
                    'sales_report_id' => $salesReport->id,
                    'outlet_id' => $menuItem ? $menuItem->outlet_id : null,
                    'menu_item_id' => $menuItem ? $menuItem->id : null,
                    'item_name' => $item->item_name,
                    'category_name' => $item->category_name,
                    'quantity_sold' => $item->quantity_sold,
                    'amount' => $item->total_revenue,
                ]);
            }

            // Generate Inventory Usage Report
            $inventoryTransactions = DB::table('inventory_transactions')
                ->where('hotel_id', $hotelId)
                ->where('type', 'deduction')
                ->whereDate('created_at', $date)
                ->get();

            $groupedInventory = $inventoryTransactions->groupBy('inventory_item_id');

            foreach ($groupedInventory as $itemId => $transactions) {
                $inventoryItem = \App\Models\InventoryItem::find($itemId);
                
                InventoryUsageReport::updateOrCreate(
                    [
                        'hotel_id' => $hotelId,
                        'outlet_id' => $inventoryItem->outlet_id,
                        'inventory_item_id' => $itemId,
                        'report_date' => $date,
                    ],
                    [
                        'quantity_used' => collect($transactions)->sum('quantity'),
                        'cost_value' => collect($transactions)->sum('quantity') * ($inventoryItem->cost_per_unit ?? 0),
                    ]
                );
            }
        });
    }
}
