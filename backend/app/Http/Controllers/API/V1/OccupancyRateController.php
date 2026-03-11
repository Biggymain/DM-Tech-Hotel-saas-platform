<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\OccupancyRateRule;
use Illuminate\Http\Request;

class OccupancyRateController extends Controller
{
    public function index()
    {
        return OccupancyRateRule::where('hotel_id', app('tenant_id'))->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rate_plan_id' => 'required|exists:rate_plans,id',
            'occupancy_threshold' => 'required|integer|min:0|max:100',
            'price_modifier_percentage' => 'required|numeric',
        ]);

        $validated['hotel_id'] = app('tenant_id');

        // Check if rate plan belongs to tenant
        $ratePlan = \App\Models\RatePlan::find($validated['rate_plan_id']);
        if ($ratePlan->hotel_id !== app('tenant_id')) {
            abort(403);
        }

        $rule = OccupancyRateRule::create($validated);
        return response()->json($rule, 201);
    }

    public function update(Request $request, OccupancyRateRule $occupancyRateRule)
    {
        if ($occupancyRateRule->hotel_id !== app('tenant_id')) {
            abort(403);
        }

        $validated = $request->validate([
            'occupancy_threshold' => 'integer|min:0|max:100',
            'price_modifier_percentage' => 'numeric',
        ]);

        $occupancyRateRule->update($validated);
        return response()->json($occupancyRateRule);
    }

    public function destroy(OccupancyRateRule $occupancyRateRule)
    {
        if ($occupancyRateRule->hotel_id !== app('tenant_id')) {
            abort(403);
        }

        $occupancyRateRule->delete();
        return response()->noContent();
    }
}
