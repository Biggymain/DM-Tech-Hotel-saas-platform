<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MenuItem;
use App\Models\MenuItemIngredient;

class MenuRecipeController extends Controller
{
    public function index(Request $request, $menuItemId)
    {
        $hotelId = $request->user()->hotel_id;
        $menuItem = MenuItem::where('hotel_id', $hotelId)->findOrFail($menuItemId);

        return response()->json($menuItem->ingredients()->with('inventoryItem')->get());
    }

    public function store(Request $request, $menuItemId)
    {
        $hotelId = $request->user()->hotel_id;
        $menuItem = MenuItem::where('hotel_id', $hotelId)->findOrFail($menuItemId);

        $validated = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'quantity_required' => 'required|numeric|min:0.01'
        ]);

        $ingredient = $menuItem->ingredients()->create($validated);

        return response()->json($ingredient->load('inventoryItem'), 201);
    }

    public function destroy(Request $request, $menuItemId, $ingredientId)
    {
        $hotelId = $request->user()->hotel_id;
        $menuItem = MenuItem::where('hotel_id', $hotelId)->findOrFail($menuItemId);
        
        $ingredient = $menuItem->ingredients()->findOrFail($ingredientId);
        $ingredient->delete();

        return response()->json(['message' => 'Recipe ingredient removed successfully']);
    }
}
