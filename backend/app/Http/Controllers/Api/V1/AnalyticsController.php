<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\RevenueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    protected $revenueService;

    public function __construct(RevenueService $revenueService)
    {
        $this->revenueService = $revenueService;
    }

    /**
     * Get a summary of revenue for the authenticated hotel.
     */
    public function revenueSummary(Request $request): JsonResponse
    {
        $hotel = $request->user()->hotel;

        if (!$hotel) {
            return response()->json(['error' => 'No hotel context found.'], 404);
        }

        $outletId = $request->query('outlet_id');
        $startDate = $request->query('start_date') ? Carbon::parse($request->query('start_date')) : null;
        $endDate = $request->query('end_date') ? Carbon::parse($request->query('end_date')) : null;

        $summary = $this->revenueService->getRevenueSummary(
            $hotel->id,
            $outletId ? (int)$outletId : null,
            $startDate,
            $endDate
        );

        return response()->json([
            'status' => 'success',
            'data' => $summary
        ]);
    }
}
