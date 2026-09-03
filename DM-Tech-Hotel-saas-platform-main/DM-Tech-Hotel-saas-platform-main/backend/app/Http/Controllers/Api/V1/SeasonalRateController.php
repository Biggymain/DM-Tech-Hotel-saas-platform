<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use App\Models\SeasonalRate;
use Illuminate\Http\Request;

class SeasonalRateController extends Controller
{
    public function index()
    {
        return SeasonalRate::where('hotel_id', app('tenant_id'))->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rate_plan_id' => 'required|exists:rate_plans,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'price_modifier' => 'required|numeric',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'string|in:mon,tue,wed,thu,fri,sat,sun',
        ]);

        $validated['hotel_id'] = app('tenant_id');

        // Check if rate plan belongs to tenant
        $ratePlan = \App\Models\RatePlan::find($validated['rate_plan_id']);
        if ($ratePlan->hotel_id !== app('tenant_id')) {
            abort(403);
        }

        $seasonalRate = SeasonalRate::create($validated);
        return response()->json($seasonalRate, 201);
    }

    public function update(Request $request, SeasonalRate $seasonalRate)
    {
        if ($seasonalRate->hotel_id !== app('tenant_id')) {
            abort(403);
        }

        $validated = $request->validate([
            'start_date' => 'date',
            'end_date' => 'date|after_or_equal:start_date',
            'price_modifier' => 'numeric',
            'days_of_week' => 'nullable|array',
            'days_of_week.*' => 'string|in:mon,tue,wed,thu,fri,sat,sun',
        ]);

        $seasonalRate->update($validated);
        return response()->json($seasonalRate);
    }

    public function destroy(SeasonalRate $seasonalRate)
    {
        if ($seasonalRate->hotel_id !== app('tenant_id')) {
            abort(403);
        }

        $seasonalRate->delete();
        return response()->noContent();
    }
}
