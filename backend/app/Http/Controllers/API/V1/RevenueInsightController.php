<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\RevenueConfig;
use App\Models\RevenueInsight;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevenueInsightController extends Controller
{
    /**
     * Get revenue insights for a date range.
     */
    public function index(Request $request): JsonResponse
    {
        $startDate = $request->query('start_date', Carbon::today()->toDateString());
        $endDate = $request->query('end_date', Carbon::today()->addDays(30)->toDateString());

        $insights = RevenueInsight::whereBetween('date', [$startDate, $endDate])
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $insights
        ]);
    }

    /**
     * Get current summary/metrics and recommendations.
     */
    public function summary(): JsonResponse
    {
        $today = Carbon::today()->toDateString();
        
        $todayInsight = RevenueInsight::where('date', $today)->first();
        
        // Get average forecast for next 7 days
        $next7Days = RevenueInsight::whereBetween('date', [
            $today, 
            Carbon::today()->addDays(7)->toDateString()
        ])->get();

        $avgOccupancy = $next7Days->avg('occupancy_rate') ?? 0;
        $avgDemand = $next7Days->avg('demand_score') ?? 0;

        // Recommendations are stored in JSON
        $recentRecommendations = $next7Days->pluck('recommended_rate_adjustment')
            ->flatten(1)
            ->filter()
            ->unique(fn($rec) => $rec['room_type_id'] . $rec['adjustment_percent'])
            ->values();

        // Revenue Config (Phase 3)
        $config = RevenueConfig::where('hotel_id', auth()->user()->hotel_id)->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'today' => $todayInsight,
                'forecast' => [
                    'avg_occupancy_7d' => round($avgOccupancy, 1),
                    'avg_demand_7d' => round($avgDemand, 1),
                ],
                'recommendations' => $recentRecommendations,
                'config' => $config
            ]
        ]);
    }

    /**
     * Update revenue configuration (auto-apply settings).
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $request->validate([
            'auto_apply_enabled' => 'nullable|boolean',
            'rules' => 'nullable|array'
        ]);

        $config = RevenueConfig::updateOrCreate(
            ['hotel_id' => auth()->user()->hotel_id],
            [
                'auto_apply_enabled' => $request->input('auto_apply_enabled', false),
                'rules' => $request->input('rules', [])
            ]
        );

        return response()->json([
            'status' => 'success',
            'data' => $config,
            'message' => 'Revenue configuration updated successfully.'
        ]);
    }

    /**
     * Manually trigger insight generation.
     */
    public function triggerSync(): JsonResponse
    {
        \App\Jobs\GenerateRevenueInsightsJob::dispatch();

        return response()->json([
            'status' => 'success',
            'message' => 'Revenue insights generation has been dispatched.'
        ]);
    }
}
