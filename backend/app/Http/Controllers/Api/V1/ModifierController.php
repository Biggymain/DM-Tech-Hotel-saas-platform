<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Modifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModifierController extends Controller
{
    public function index()
    {
        return response()->json(Modifier::with('modifierOptions')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'min_selections' => 'integer|min:0',
            'max_selections' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'options' => 'nullable|array',
            'options.*.name' => 'required_with:options|string|max:255',
            'options.*.price_adjustment' => 'numeric',
            'options.*.is_active' => 'boolean',
            'options.*.display_order' => 'integer'
        ]);

        $modifier = DB::transaction(function () use ($validated) {
            $options = $validated['options'] ?? [];
            unset($validated['options']);
            
            $modifier = Modifier::create($validated);
            
            if (!empty($options)) {
                $modifier->modifierOptions()->createMany($options);
            }
            return $modifier;
        });

        return response()->json($modifier->load('modifierOptions'), 201);
    }

    public function show($id)
    {
        $modifier = Modifier::with('modifierOptions')->findOrFail($id);
        return response()->json($modifier);
    }

    public function update(Request $request, $id)
    {
        $modifier = Modifier::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'min_selections' => 'integer|min:0',
            'max_selections' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'options' => 'nullable|array',
            'options.*.id' => 'nullable|exists:modifier_options,id',
            'options.*.name' => 'required_with:options|string|max:255',
            'options.*.price_adjustment' => 'numeric',
            'options.*.is_active' => 'boolean',
            'options.*.display_order' => 'integer'
        ]);

        DB::transaction(function () use ($modifier, $validated) {
            $options = $validated['options'] ?? null;
            unset($validated['options']);
            
            $modifier->update($validated);

            if ($options !== null) {
                $existingOptionIds = [];
                foreach ($options as $optionData) {
                    if (isset($optionData['id'])) {
                        $option = $modifier->modifierOptions()->find($optionData['id']);
                        if ($option) {
                            $option->update($optionData);
                            $existingOptionIds[] = $option->id;
                        }
                    } else {
                        $newOption = $modifier->modifierOptions()->create($optionData);
                        $existingOptionIds[] = $newOption->id;
                    }
                }
                // Optional: Delete options not in the request
                $modifier->modifierOptions()->whereNotIn('id', $existingOptionIds)->delete();
            }
        });

        return response()->json($modifier->load('modifierOptions'));
    }

    public function destroy($id)
    {
        $modifier = Modifier::findOrFail($id);
        $modifier->delete();
        return response()->json(null, 204);
    }
}
