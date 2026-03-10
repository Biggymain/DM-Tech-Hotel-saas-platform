<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\InventoryItem;
use Illuminate\Validation\Rule;

class InventoryItemController extends Controller
{
    public function index(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        $query = InventoryItem::where('hotel_id', $hotelId)->with('outlet');

        if ($request->query('low_stock')) {
            $query->whereRaw('current_stock <= minimum_stock_level');
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        
        $validated = $request->validate([
            'outlet_id' => 'nullable|exists:outlets,id',
            'name' => 'required|string|max:255',
            'sku' => [
                'required', 'string', 'max:100',
                Rule::unique('inventory_items')->where('hotel_id', $hotelId)
            ],
            'category' => 'nullable|string|max:100',
            'unit_of_measurement' => 'required|string|max:50',
            'minimum_stock_level' => 'numeric|min:0',
            'current_stock' => 'numeric|min:0',
            'cost_per_unit' => 'numeric|min:0',
            'status' => 'string|in:active,inactive'
        ]);
        
        $validated['hotel_id'] = $hotelId;

        $item = InventoryItem::create($validated);
        
        return response()->json($item, 201);
    }

    public function show(Request $request, $id)
    {
        $item = InventoryItem::where('hotel_id', $request->user()->hotel_id)
            ->with('outlet')
            ->findOrFail($id);
            
        return response()->json($item);
    }

    public function update(Request $request, $id)
    {
        $hotelId = $request->user()->hotel_id;
        $item = InventoryItem::where('hotel_id', $hotelId)->findOrFail($id);

        $validated = $request->validate([
            'outlet_id' => 'nullable|exists:outlets,id',
            'name' => 'string|max:255',
            'sku' => [
                'string', 'max:100',
                Rule::unique('inventory_items')->where('hotel_id', $hotelId)->ignore($item->id)
            ],
            'category' => 'nullable|string|max:100',
            'unit_of_measurement' => 'string|max:50',
            'minimum_stock_level' => 'numeric|min:0',
            'cost_per_unit' => 'numeric|min:0',
            'status' => 'string|in:active,inactive'
        ]);

        $item->update($validated);

        return response()->json($item);
    }

    public function destroy(Request $request, $id)
    {
        $hotelId = $request->user()->hotel_id;
        $item = InventoryItem::where('hotel_id', $hotelId)->findOrFail($id);
        $item->delete(); // Soft delete

        return response()->json(['message' => 'Inventory item deleted successfully']);
    }
}
