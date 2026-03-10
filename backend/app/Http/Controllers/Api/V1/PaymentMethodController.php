<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;

class PaymentMethodController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(PaymentMethod::where('hotel_id', $request->user()->hotel_id)->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'boolean',
            'requires_reference' => 'boolean'
        ]);

        $paymentMethod = PaymentMethod::create(array_merge($validated, [
            'hotel_id' => $request->user()->hotel_id
        ]));

        return response()->json($paymentMethod, 201);
    }

    public function show(Request $request, string $id)
    {
        $method = PaymentMethod::where('hotel_id', $request->user()->hotel_id)->findOrFail($id);
        return response()->json($method);
    }

    public function update(Request $request, string $id)
    {
        $method = PaymentMethod::where('hotel_id', $request->user()->hotel_id)->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'is_active' => 'boolean',
            'requires_reference' => 'boolean'
        ]);

        $method->update($validated);

        return response()->json($method);
    }

    public function destroy(Request $request, string $id)
    {
        $method = PaymentMethod::where('hotel_id', $request->user()->hotel_id)->findOrFail($id);
        $method->delete();

        return response()->json(null, 204);
    }
}
