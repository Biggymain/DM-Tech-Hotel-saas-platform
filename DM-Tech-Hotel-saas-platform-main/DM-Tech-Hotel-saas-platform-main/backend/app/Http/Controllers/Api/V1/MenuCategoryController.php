<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use App\Models\MenuCategory;
use Illuminate\Http\Request;

class MenuCategoryController extends Controller
{
    protected function getHotelId(Request $request)
    {
        return app()->bound('tenant_id') ? app('tenant_id') : $request->user()->hotel_id;
    }

    public function index()
    {
        return response()->json(MenuCategory::all());
    }

    public function store(Request $request)
    {
        $hotelId = $this->getHotelId($request);
        if (!$hotelId) {
             return response()->json(['error' => 'No hotel context for category creation'], 400);
        }

        $validated = $request->validate([
            'outlet_id' => 'nullable|exists:outlets,id',
            'name' => 'required|string|max:255',
            'station' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'display_order' => 'integer'
        ]);

        $validated['hotel_id'] = $hotelId;
        $category = MenuCategory::create($validated);
        return response()->json($category, 201);
    }

    public function show($id)
    {
        $category = MenuCategory::findOrFail($id);
        return response()->json($category);
    }

    public function update(Request $request, $id)
    {
        $category = MenuCategory::findOrFail($id);
        
        $validated = $request->validate([
            'outlet_id' => 'nullable|exists:outlets,id',
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'display_order' => 'integer'
        ]);

        $category->update($validated);
        return response()->json($category);
    }

    public function destroy($id)
    {
        $category = MenuCategory::findOrFail($id);
        $category->delete();
        return response()->json(null, 204);
    }
}
