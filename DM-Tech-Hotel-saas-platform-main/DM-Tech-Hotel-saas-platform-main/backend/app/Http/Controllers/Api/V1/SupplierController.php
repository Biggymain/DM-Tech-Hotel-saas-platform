<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use Illuminate\Http\Request;
use App\Models\Supplier;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        return response()->json(Supplier::where('hotel_id', $hotelId)->get());
    }

    public function store(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'status' => 'string|in:active,inactive'
        ]);
        
        $validated['hotel_id'] = $hotelId;

        $supplier = Supplier::create($validated);
        return response()->json($supplier, 201);
    }

    public function show(Request $request, $id)
    {
        $supplier = Supplier::where('hotel_id', $request->user()->hotel_id)->findOrFail($id);
        return response()->json($supplier);
    }

    public function update(Request $request, $id)
    {
        $supplier = Supplier::where('hotel_id', $request->user()->hotel_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'status' => 'string|in:active,inactive'
        ]);

        $supplier->update($validated);
        return response()->json($supplier);
    }

    public function destroy(Request $request, $id)
    {
        $supplier = Supplier::where('hotel_id', $request->user()->hotel_id)->findOrFail($id);
        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted successfully']);
    }
}
