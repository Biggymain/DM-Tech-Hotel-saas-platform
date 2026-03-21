<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\Request;

class OutletController extends Controller
{
    public function index()
    {
        return response()->json(Outlet::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:restaurant,bar,cafe,store,other',
            'is_active' => 'boolean',
        ]);

        $outlet = Outlet::create($validated);
        return response()->json($outlet, 201);
    }

    public function show($id)
    {
        $outlet = Outlet::findOrFail($id);
        return response()->json($outlet);
    }

    public function update(Request $request, $id)
    {
        $outlet = Outlet::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'type' => 'string|in:restaurant,bar,cafe,store,other',
            'is_active' => 'boolean',
        ]);

        $outlet->update($validated);
        return response()->json($outlet);
    }

    public function destroy($id)
    {
        $outlet = Outlet::findOrFail($id);
        $outlet->delete();
        return response()->json(null, 204);
    }
}
