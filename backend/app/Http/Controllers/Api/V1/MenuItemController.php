<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use App\Models\MenuItem;
use Illuminate\Http\Request;

class MenuItemController extends Controller
{
    public function index()
    {
        return response()->json(MenuItem::with(['menuCategory', 'department', 'modifiers.modifierOptions'])->get());
    }

    public function store(Request $request)
    {
        $hotelId = app()->bound('tenant_id') ? app('tenant_id') : $request->user()->hotel_id;

        $validated = $request->validate([
            'outlet_id' => 'nullable|exists:outlets,id',
            'menu_category_id' => 'nullable|exists:menu_categories,id',
            'department_id' => 'nullable|exists:departments,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'image_url' => 'nullable|string',
            'is_available' => 'boolean',
            'is_active' => 'boolean',
            'display_order' => 'integer',
            'modifier_ids' => 'nullable|array',
            'modifier_ids.*' => 'exists:modifiers,id',
            'station_id' => 'nullable|exists:stations,id',
            'station_name' => 'nullable|string'
        ]);

        $modifierIds = $validated['modifier_ids'] ?? null;
        unset($validated['modifier_ids']);

        $validated['hotel_id'] = $hotelId;
        $item = MenuItem::create($validated);
        
        if ($modifierIds !== null) {
            $item->modifiers()->sync($modifierIds);
        }
        
        return response()->json($item->load('modifiers'), 201);
    }

    public function show($id)
    {
        $item = MenuItem::with(['menuCategory', 'department', 'modifiers.modifierOptions'])->findOrFail($id);
        return response()->json($item);
    }

    public function update(Request $request, $id)
    {
        $item = MenuItem::findOrFail($id);
        
        $validated = $request->validate([
            'outlet_id' => 'nullable|exists:outlets,id',
            'menu_category_id' => 'nullable|exists:menu_categories,id',
            'department_id' => 'nullable|exists:departments,id',
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'price' => 'numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'image_url' => 'nullable|string',
            'is_available' => 'boolean',
            'is_active' => 'boolean',
            'display_order' => 'integer',
            'modifier_ids' => 'nullable|array',
            'modifier_ids.*' => 'exists:modifiers,id'
        ]);

        $modifierIds = $validated['modifier_ids'] ?? null;
        unset($validated['modifier_ids']);

        $item->update($validated);

        if ($modifierIds !== null) {
            $item->modifiers()->sync($modifierIds);
        }

        return response()->json($item->load('modifiers'));
    }

    public function destroy($id)
    {
        $item = MenuItem::findOrFail($id);
        $item->delete();
        return response()->json(null, 204);
    }

    /**
     * Copy an item from one outlet (e.g. Store) to another with a new price.
     */
    public function duplicateToOutlet(Request $request, $id)
    {
        $sourceItem = MenuItem::findOrFail($id);
        
        $validated = $request->validate([
            'outlet_id' => 'required|exists:outlets,id',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $newItem = $sourceItem->replicate();
        $newItem->outlet_id = $validated['outlet_id'];
        $newItem->price = $validated['price'];
        $newItem->is_active = $validated['is_active'] ?? true;
        $newItem->save();

        // Copy modifiers
        if ($sourceItem->modifiers->count() > 0) {
            $newItem->modifiers()->sync($sourceItem->modifiers->pluck('id'));
        }

        return response()->json($newItem->load('modifiers'), 201);
    }
}
