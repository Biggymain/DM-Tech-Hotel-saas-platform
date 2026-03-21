<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use App\Models\RatePlan;
use Illuminate\Http\Request;

class RatePlanController extends Controller
{
    public function index()
    {
        return RatePlan::with(['roomTypes', 'seasonalRates', 'occupancyRules'])
            ->where('hotel_id', app('tenant_id'))
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'pricing_strategy' => 'required|in:fixed,seasonal,occupancy',
            'base_price_modifier' => 'numeric',
            'is_active' => 'boolean',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|gte:min_price',
            'room_types' => 'array',
            'room_types.*.id' => 'required|exists:room_types,id',
            'room_types.*.base_price' => 'required|numeric|min:0'
        ]);

        $validated['hotel_id'] = app('tenant_id');

        $ratePlanData = collect($validated)->except('room_types')->toArray();
        $ratePlan = RatePlan::create($ratePlanData);

        if (!empty($validated['room_types'])) {
            $syncData = [];
            foreach ($validated['room_types'] as $rt) {
                // Attach the room type with its specific base price constraint
                $syncData[$rt['id']] = ['base_price' => $rt['base_price'], 'hotel_id' => app('tenant_id')];
            }
            $ratePlan->roomTypes()->sync($syncData);
        }

        return response()->json($ratePlan->load('roomTypes'), 201);
    }

    public function update(Request $request, RatePlan $ratePlan)
    {
        if ($ratePlan->hotel_id !== app('tenant_id')) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'pricing_strategy' => 'in:fixed,seasonal,occupancy',
            'base_price_modifier' => 'numeric',
            'is_active' => 'boolean',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|gte:min_price',
            'room_types' => 'array',
            'room_types.*.id' => 'required|exists:room_types,id',
            'room_types.*.base_price' => 'required|numeric|min:0'
        ]);

        $ratePlanData = collect($validated)->except('room_types')->toArray();
        $ratePlan->update($ratePlanData);

        if (isset($validated['room_types'])) {
             $syncData = [];
             foreach ($validated['room_types'] as $rt) {
                 $syncData[$rt['id']] = ['base_price' => $rt['base_price'], 'hotel_id' => app('tenant_id')];
             }
             $ratePlan->roomTypes()->sync($syncData);
        }

        return response()->json($ratePlan->load('roomTypes'));
    }

    public function destroy(RatePlan $ratePlan)
    {
        if ($ratePlan->hotel_id !== app('tenant_id')) {
            abort(403);
        }

        $ratePlan->delete();
        return response()->noContent();
    }
}
