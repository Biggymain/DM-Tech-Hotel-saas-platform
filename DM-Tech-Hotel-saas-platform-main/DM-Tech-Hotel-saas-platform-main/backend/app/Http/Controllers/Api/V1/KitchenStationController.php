<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KitchenStation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KitchenStationController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $user = $request->user();
        $stations = KitchenStation::where(['hotel_id' => $user->hotel_id])
            ->where(['branch_id' => $user->branch_id ?? $user->hotel_id])
            ->get();
        return response()->json($stations);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $station = KitchenStation::create([
            ...$validated,
            'hotel_id' => $user->hotel_id,
            'branch_id' => $user->branch_id ?? $user->hotel_id,
            'slug' => Str::slug($validated['name']),
        ]);

        return response()->json($station, 201);
    }

    public function show(KitchenStation $kitchenStation)
    {
        $this->authorize('view', $kitchenStation);
        return response()->json($kitchenStation);
    }

    public function update(Request $request, KitchenStation $kitchenStation)
    {
        $this->authorize('update', $kitchenStation);
        
        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $kitchenStation->update($validated);

        return response()->json($kitchenStation);
    }

    public function destroy(KitchenStation $kitchenStation)
    {
        $this->authorize('delete', $kitchenStation);
        $kitchenStation->delete();
        return response()->json(null, 204);
    }
}
