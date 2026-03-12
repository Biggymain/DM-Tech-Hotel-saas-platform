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
        $user = $request->user();
        $hotelId = $user->hotel_id;
        $outletId = $user->isKitchenManager() ? $user->outlet_id : null;
        
        $cacheKey = "dashboard_occupancy_{$hotelId}" . ($outletId ? "_outlet_{$outletId}" : "");

        $data = Cache::remember($cacheKey, 30, function () use ($hotelId, $outletId) {
            $query = Room::where('hotel_id', $hotelId);
            
            // Note: If rooms are linked to outlets, apply filter. 
            // Currently rooms are usually hotel-wide, but we can filter associated orders/tasks if needed.
            // For this specific requirement, we filter metrics based on user role.

            return [
                'total_rooms' => (clone $query)->count(),
                'occupied_rooms' => (clone $query)->where('status', 'occupied')->count(),
                'available_rooms' => (clone $query)->where('status', 'available')->count(),
                'cleaning_rooms' => (clone $query)->where('housekeeping_status', 'dirty')->count(),
                'maintenance_rooms' => (clone $query)->where('status', 'maintenance')->count(),
            ];
        });

        return response()->json($data);
    }

    /**
     * Get revenue snapshot with caching.
     */
    public function revenue(Request $request)
    {
        $user = $request->user();
        $hotelId = $user->hotel_id;
        $outletId = $user->isKitchenManager() ? $user->outlet_id : null;

        $cacheKey = "dashboard_revenue_{$hotelId}" . ($outletId ? "_outlet_{$outletId}" : "");

        $data = Cache::remember($cacheKey, 30, function () use ($hotelId, $outletId) {
            $today = now()->startOfDay();
            $query = Order::where('hotel_id', $hotelId)->where('created_at', '>=', $today);

            if ($outletId) {
                $query->where('outlet_id', $outletId);
            }

            $totalRevenue = (clone $query)->where('payment_status', 'paid')->sum('total_amount');

            $restaurantRevenue = (clone $query)->where('payment_status', 'paid')
                ->whereIn('order_source', ['pos', 'qr_table'])
                ->sum('total_amount');

            $roomRevenue = (clone $query)->where('payment_status', 'paid')
                ->where('order_source', 'room_service')
                ->sum('total_amount');

            $pendingPayments = (clone $query)->where('payment_status', 'unpaid')
                ->sum('total_amount');

            return [
                'today_revenue' => (float) $totalRevenue,
                'restaurant_revenue' => (float) $restaurantRevenue,
                'room_revenue' => (float) $roomRevenue,
                'pending_payments' => (float) $pendingPayments,
                'currency' => 'USD',
            ];
        });

        return response()->json($data);
    }

    /**
     * Get operational summary for live widgets.
     */
    public function operations(Request $request)
    {
        $user = $request->user();
        $hotelId = $user->hotel_id;
        $outletId = $user->isKitchenManager() ? $user->outlet_id : null;

        $cacheKey = "dashboard_operations_{$hotelId}" . ($outletId ? "_outlet_{$outletId}" : "");

        $data = Cache::remember($cacheKey, 30, function () use ($hotelId, $outletId) {
            $reservationQuery = \App\Models\Reservation::where('hotel_id', $hotelId);
            $orderQuery = \App\Models\Order::where('hotel_id', $hotelId);
            
            if ($outletId) {
                $orderQuery->where('outlet_id', $outletId);
            }

            return [
                'active_guests' => $reservationQuery->where('status', 'checked_in')->count(),
                'pending_service_requests' => \App\Models\GuestServiceRequest::where('hotel_id', $hotelId)
                    ->whereIn('status', ['pending', 'in_progress'])->count(),
                'active_housekeeping' => \App\Models\HousekeepingTask::where('hotel_id', $hotelId)
                    ->whereIn('status', ['pending', 'in_progress'])->count(),
                'open_maintenance' => \App\Models\MaintenanceRequest::where('hotel_id', $hotelId)
                    ->whereIn('status', ['pending', 'in_progress'])->count(),
                'recent_orders' => $orderQuery->latest()->take(5)->get(),
            ];
        });

        return response()->json($data);
    }
}
