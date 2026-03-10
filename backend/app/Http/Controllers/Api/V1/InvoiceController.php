<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::where('hotel_id', $request->user()->hotel_id)->with(['items', 'payments']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        return response()->json($query->orderBy('created_at', 'desc')->paginate(20));
    }

    public function show(Request $request, string $id)
    {
        $invoice = Invoice::where('hotel_id', $request->user()->hotel_id)
            ->with(['items', 'payments', 'order'])
            ->findOrFail($id);

        return response()->json($invoice);
    }

    public function update(Request $request, string $id)
    {
        $invoice = Invoice::where('hotel_id', $request->user()->hotel_id)->findOrFail($id);

        $validated = $request->validate([
            'notes' => 'nullable|string',
            'due_date' => 'nullable|date',
            'status' => 'nullable|in:pending,partially_paid,paid,partially_refunded,refunded,cancelled'
        ]);

        $invoice->update($validated);

        return response()->json($invoice);
    }

    public function destroy(string $id)
    {
        return response()->json(['message' => 'Deletion of invoices is prohibited via this endpoint.'], 403);
    }
}
