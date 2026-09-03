<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Folio;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RevenueService
{
    /**
     * Get aggregated revenue summary for a hotel/branch.
     * Includes Gross/Net revenue and outlet breakdown.
     */
    public function getRevenueSummary(int $hotelId, ?int $outletId = null, ?Carbon $start = null, ?Carbon $end = null)
    {
        // 1. Base Query for Invoices (Source of Realized Revenue)
        $invoiceQuery = Invoice::where('hotel_id', $hotelId)
            ->where('status', '!=', 'voided');
            
        if ($outletId) {
            $invoiceQuery->where('outlet_id', $outletId);
        }

        if ($start && $end) {
            $invoiceQuery->whereBetween('updated_at', [$start, $end]);
        }

        // Accrued Revenue: Sum of total_amount from all active invoices (earned but not necessarily paid)
        $accruedRevenue = $invoiceQuery->sum('total_amount');

        // Cash-on-Hand: Sum of amount_paid from all active invoices (actual liquidity collected)
        $cashOnHand = $invoiceQuery->sum('amount_paid');

        // Net Revenue: Sum of total_amount ONLY for invoices marked as 'paid' (fully cleared)
        $netRevenue = (clone $invoiceQuery)->where('status', 'paid')->sum('total_amount');

        // Discounts
        $discounts = $invoiceQuery->sum('discount_amount');

        // 2. POS Specific Aggregation for counts
        $orderQuery = Order::where('hotel_id', $hotelId)
            ->whereNotIn('order_status', ['voided', 'draft']);

        if ($outletId) {
            $orderQuery->where('outlet_id', $outletId);
        }

        $transactionCount = $orderQuery->count();

        // 3. Outlet Breakdown
        $outletBreakdown = Invoice::where('hotel_id', $hotelId)
            ->where('status', '!=', 'voided')
            ->groupBy('outlet_id')
            ->select(
                'outlet_id',
                DB::raw('SUM(total_amount) as total'),
                DB::raw('SUM(amount_paid) as paid'),
                DB::raw('COUNT(*) as count')
            )
            ->get();

        return [
            'hotel_id' => $hotelId,
            'period' => [
                'start' => $start ? $start->toDateTimeString() : null,
                'end' => $end ? $end->toDateTimeString() : null,
            ],
            'metrics' => [
                'accrued_revenue' => round($accruedRevenue, 2),
                'cash_on_hand'    => round($cashOnHand, 2),
                'net_revenue'     => round($netRevenue, 2),
                'pending_revenue' => round($accruedRevenue - $cashOnHand, 2),
                'discounts'       => round($discounts, 2),
                'transaction_count' => $transactionCount,
            ],
            'outlet_breakdown' => $outletBreakdown
        ];
    }

    /**
     * Get revenue attribution by staff member using item-level liability.
     */
    public function getStaffPerformance(int $hotelId, ?Carbon $start = null, ?Carbon $end = null)
    {
        $query = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.hotel_id', $hotelId)
            ->whereNotIn('order_items.status', ['voided', 'returned'])
            ->whereNotNull('order_items.waiter_id')
            ->groupBy('order_items.waiter_id')
            ->select(
                'order_items.waiter_id',
                DB::raw('SUM(order_items.subtotal) as total_sales'),
                DB::raw('COUNT(order_items.id) as items_sold')
            );
            
        if ($start && $end) {
            $query->whereBetween('order_items.updated_at', [$start, $end]);
        }

        return $query->get();
    }
}
