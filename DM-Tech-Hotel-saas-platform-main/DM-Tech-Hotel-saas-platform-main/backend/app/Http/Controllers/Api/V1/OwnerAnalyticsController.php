<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\InventoryItem;
use App\Models\GuestPortalSession;
use App\Models\Hotel;
use App\Models\Scopes\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OwnerAnalyticsController extends Controller
{
    /**
     * Platform Analytics for hotel admins (hotel-scoped, no group required).
     * Returns the ['stats', 'hotels'] shape expected by SubscriptionSystemTest.
     */
    public function platformAnalytics(Request $request)
    {
        $user    = $request->user();
        $hotelId = $user->hotel_id ?? null;

        $totalRevenue = Order::withoutGlobalScope(TenantScope::class)
            ->when($hotelId, fn($q) => $q->where('hotel_id', $hotelId))
            ->where('order_status', '!=', 'voided')
            ->sum('total_amount');

        $totalActiveSessions = GuestPortalSession::withoutGlobalScope(TenantScope::class)
            ->when($hotelId, fn($q) => $q->where('hotel_id', $hotelId))
            ->where('status', 'active')
            ->count();

        $hotels = Hotel::withoutGlobalScope(TenantScope::class)
            ->when($hotelId, fn($q) => $q->where('id', $hotelId))
            ->get(['id', 'name'])
            ->toArray();

        return response()->json([
            'stats' => [
                'total_revenue'       => (float) $totalRevenue,
                'active_sessions'     => $totalActiveSessions,
            ],
            'hotels' => $hotels,
        ]);
    }

    /**
     * Master Summary for Owners/Group Admins.
     * Aggregates data across all branches in their group.
     */
    public function masterSummary(Request $request)
    {
        $user = $request->user();

        // Security check: Only Group Owners can access master summary
        if (empty($user->hotel_group_id)) {
            return response()->json(['message' => 'Unauthorized. Owner context required.'], 403);
        }

        // Get all branch IDs belonging to the owner's group
        $branchIds = Hotel::withoutGlobalScope(TenantScope::class)
            ->where('hotel_group_id', $user->hotel_group_id)
            ->pluck('id')
            ->toArray();

        // 1. Total Revenue (Accrued across all branches)
        $totalRevenue = Order::withoutGlobalScope(TenantScope::class)
            ->whereIn('hotel_id', $branchIds)
            ->where('order_status', '!=', 'voided')
            ->sum('total_amount');

        // 2. Total Stock Value
        $totalStockValue = InventoryItem::withoutGlobalScope(TenantScope::class)
            ->whereIn('hotel_id', $branchIds)
            ->select(DB::raw('SUM(current_stock * cost_per_unit) as total_value'))
            ->value('total_value') ?? 0;

        // 3. Total Active Sessions
        $totalActiveSessions = GuestPortalSession::withoutGlobalScope(TenantScope::class)
            ->whereIn('hotel_id', $branchIds)
            ->where('status', 'active')
            ->count();

        // 4. Total COGS
        $totalCogs = \App\Models\StockTransfer::withoutGlobalScope(TenantScope::class)
            ->whereIn('stock_transfers.hotel_id', $branchIds)
            ->where('stock_transfers.status', 'completed')
            ->join('inventory_items', 'stock_transfers.inventory_item_id', '=', 'inventory_items.id')
            ->sum(DB::raw('stock_transfers.quantity_received * inventory_items.cost_per_unit'));

        $totalCogs = (float)($totalCogs ?? 0);
        $grossProfit = $totalRevenue - $totalCogs;

        return response()->json([
            'status' => 'success',
            'data' => [
                'group_name' => $user->hotelGroup?->name,
                'branch_count' => count($branchIds),
                'metrics' => [
                    'total_revenue' => (float)$totalRevenue,
                    'total_cogs' => $totalCogs,
                    'gross_profit' => $grossProfit,
                    'total_stock_value' => (float)$totalStockValue,
                    'total_active_sessions' => $totalActiveSessions,
                ],
                'timestamp' => now()->toIso8601String()
            ]
        ]);
    }

    /**
     * CSV Export Engine for Master Analytics.
     */
    public function exportMasterSummary(Request $request)
    {
        $user = $request->user();

        if (empty($user->hotel_group_id)) {
            return response()->json(['message' => 'Unauthorized. Owner context required.'], 403);
        }

        $branches = Hotel::withoutGlobalScope(TenantScope::class)
            ->where('hotel_group_id', $user->hotel_group_id)
            ->get();

        $data = [];
        foreach ($branches as $branch) {
            $revenue = Order::withoutGlobalScope(TenantScope::class)
                ->where('hotel_id', $branch->id)
                ->where('order_status', '!=', 'voided')
                ->sum('total_amount');

            $cogs = \App\Models\StockTransfer::withoutGlobalScope(TenantScope::class)
                ->where('stock_transfers.hotel_id', $branch->id)
                ->where('stock_transfers.status', 'completed')
                ->join('inventory_items', 'stock_transfers.inventory_item_id', '=', 'inventory_items.id')
                ->sum(DB::raw('stock_transfers.quantity_received * inventory_items.cost_per_unit'));

            $profit = $revenue - $cogs;

            $activeSessions = GuestPortalSession::withoutGlobalScope(TenantScope::class)
                ->where('hotel_id', $branch->id)
                ->where('status', 'active')
                ->count();

            $data[] = [
                $branch->name,
                number_format((float)$revenue, 2, '.', ''),
                number_format((float)$cogs, 2, '.', ''),
                number_format((float)$profit, 2, '.', ''),
                $activeSessions
            ];
        }

        $headers = ['Branch Name', 'Total Revenue', 'Total COGS', 'Net Profit', 'Active Guest Sessions'];

        $callback = function() use ($data, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            foreach ($data as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="owner_report.csv"'
        ]);
    }
}
