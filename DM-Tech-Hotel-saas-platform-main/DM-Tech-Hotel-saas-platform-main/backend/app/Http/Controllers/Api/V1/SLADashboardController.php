<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KitchenTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SLADashboardController extends Controller
{
    public function activeTickets(Request $request)
    {
        $user = $request->user();
        $query = KitchenTicket::with(['order', 'kitchenStation'])
            ->whereIn('status', ['queued', 'preparing']);

        // Role-based scoping
        if ($user->role === 'outletmanager') {
            $query->where(['kitchen_station_id' => $user->kitchen_station_id]);
        } elseif ($user->role === 'branchmanager') {
            $query->where(['branch_id' => $user->branch_id ?? $user->hotel_id]);
        } elseif (!$user->is_super_admin) {
             return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($query->orderBy('created_at', 'asc')->get());
    }

    public function performanceReport(Request $request)
    {
        $user = $request->user();
        $hotelId = $user->hotel_id;
        $branchId = $user->branch_id ?? $hotelId;

        $stats = KitchenTicket::select(
            'kitchen_station_id',
            DB::raw('COUNT(*) as total_tickets'),
            DB::raw('SUM(CASE WHEN status = "ready" AND (completed_at - created_at) < INTERVAL \'20 minutes\' THEN 1 ELSE 0 END) as on_time_tickets')
        )
        ->where('hotel_id', $hotelId)
        ->where('branch_id', $branchId)
        ->where('created_at', '>=', now()->subHours(24))
        ->groupBy('kitchen_station_id')
        ->with('kitchenStation')
        ->get();

        $report = $stats->map(function ($stat) {
            $successRate = $stat->total_tickets > 0 
                ? ($stat->on_time_tickets / $stat->total_tickets) * 100 
                : 0;
            
            return [
                'station_name' => $stat->kitchenStation?->name ?? 'Unknown',
                'total_tickets' => $stat->total_tickets,
                'on_time_tickets' => $stat->on_time_tickets,
                'success_rate' => round($successRate, 2),
            ];
        });

        return response()->json($report);
    }

    public function branchOverview(Request $request)
    {
        $user = $request->user();
        $branchId = $user->branch_id ?? $user->hotel_id;

        $outlets = \App\Models\KitchenStation::where('branch_id', $branchId)
            ->withCount(['tickets as active_tickets' => function($q) {
                $q->whereIn('status', ['queued', 'preparing']);
            }])
            ->withCount(['tickets as late_tickets' => function($q) {
                $q->whereIn('status', ['queued', 'preparing'])
                  ->where('created_at', '<=', now()->subMinutes(20));
            }])
            ->withCount(['restockRequests as restock_requests' => function($q) {
                $q->where('status', 'pending');
            }])
            ->get();

        $data = $outlets->map(function($outlet) {
            // Mocking avg_prep_time for now, or calculate from last 10 ready tickets
            $avgPrep = KitchenTicket::where('kitchen_station_id', $outlet->id)
                ->where('status', 'ready')
                ->where('created_at', '>=', now()->subHours(2))
                ->avg(DB::raw('EXTRACT(EPOCH FROM (completed_at - created_at))/60')) ?? 0;

            return [
                'id' => $outlet->id,
                'name' => $outlet->name,
                'active_tickets' => $outlet->active_tickets,
                'late_tickets' => $outlet->late_tickets,
                'avg_prep_time' => round($avgPrep, 1),
                'restock_requests' => $outlet->restock_requests,
            ];
        });

        return response()->json(['outlets' => $data]);
    }
}
