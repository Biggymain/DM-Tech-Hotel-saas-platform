<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Room;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get occupancy summary with caching.
     */
    public function occupancy(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        $cacheKey = "dashboard_occupancy_{$hotelId}";

        $data = Cache::remember($cacheKey, 30, function () use ($hotelId) {
            return [
                'total_rooms' => Room::where('hotel_id', $hotelId)->count(),
                'occupied_rooms' => Room::where('hotel_id', $hotelId)->where('status', 'occupied')->count(),
                'available_rooms' => Room::where('hotel_id', $hotelId)->where('status', 'available')->count(),
                'cleaning_rooms' => Room::where('hotel_id', $hotelId)->where('housekeeping_status', 'dirty')->count(),
                'maintenance_rooms' => Room::where('hotel_id', $hotelId)->where('status', 'maintenance')->count(),
            ];
        });

        return response()->json($data);
    }

    /**
     * Get revenue snapshot with caching.
     */
    public function revenue(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        $cacheKey = "dashboard_revenue_{$hotelId}";

        $data = Cache::remember($cacheKey, 30, function () use ($hotelId) {
            $today = now()->startOfDay();

            $totalRevenue = Order::where('hotel_id', $hotelId)
                ->where('created_at', '>=', $today)
                ->where('payment_status', 'paid')
                ->sum('total_amount');

            $restaurantRevenue = Order::where('hotel_id', $hotelId)
                ->where('created_at', '>=', $today)
                ->where('payment_status', 'paid')
                ->whereIn('order_source', ['pos', 'qr_table'])
                ->sum('total_amount');

            $roomRevenue = Order::where('hotel_id', $hotelId)
                ->where('created_at', '>=', $today)
                ->where('payment_status', 'paid')
                ->where('order_source', 'room_service')
                ->sum('total_amount');

            $pendingPayments = Order::where('hotel_id', $hotelId)
                ->where('created_at', '>=', $today)
                ->where('payment_status', 'unpaid')
                ->sum('total_amount');

            return [
                'today_revenue' => (float) $totalRevenue,
                'restaurant_revenue' => (float) $restaurantRevenue,
                'room_revenue' => (float) $roomRevenue,
                'pending_payments' => (float) $pendingPayments,
                'currency' => 'USD', // Could be dynamic based on hotel settings
            ];
        });

        return response()->json($data);
    }

    /**
     * Get operational summary for live widgets.
     */
    public function operations(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        $cacheKey = "dashboard_operations_{$hotelId}";

        $data = Cache::remember($cacheKey, 30, function () use ($hotelId) {
            return [
                'active_guests' => \App\Models\Reservation::where('hotel_id', $hotelId)
                    ->where('status', 'checked_in')->count(),
                'pending_service_requests' => \App\Models\GuestServiceRequest::where('hotel_id', $hotelId)
                    ->whereIn('status', ['pending', 'in_progress'])->count(),
                'active_housekeeping' => \App\Models\HousekeepingTask::where('hotel_id', $hotelId)
                    ->whereIn('status', ['pending', 'in_progress'])->count(),
                'open_maintenance' => \App\Models\MaintenanceRequest::where('hotel_id', $hotelId)
                    ->whereIn('status', ['pending', 'in_progress'])->count(),
                'recent_orders' => \App\Models\Order::where('hotel_id', $hotelId)
                    ->latest()
                    ->take(5)
                    ->get(),
            ];
        });

        return response()->json($data);
    }
}
