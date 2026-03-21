<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\BillingService;

class PaymentController extends Controller
{
    protected BillingService $billingService;

    public function __construct(BillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    public function index(Request $request)
    {
        $query = Payment::where('hotel_id', $request->user()->hotel_id)->with(['invoice', 'paymentMethod']);

        if ($request->has('invoice_id')) {
            $query->where('invoice_id', $request->invoice_id);
        }

        return response()->json($query->orderBy('created_at', 'desc')->paginate(20));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'transaction_reference' => 'nullable|string'
        ]);

        $invoice = Invoice::where('hotel_id', $request->user()->hotel_id)->findOrFail($validated['invoice_id']);

        try {
            $payment = $this->billingService->processPayment(
                $invoice,
                $validated['amount'],
                $validated['payment_method_id'],
                $request->user()->id,
                $validated['transaction_reference'] ?? null
            );

            return response()->json($payment->load('paymentMethod'), 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function refund(Request $request, string $id)
    {
        $payment = Payment::where('hotel_id', $request->user()->hotel_id)
            ->where('type', 'payment')
            ->findOrFail($id);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string'
        ]);

        try {
            $refund = $this->billingService->processRefund(
                $payment,
                $validated['amount'],
                $request->user()->id,
                $validated['notes'] ?? null
            );

            return response()->json($refund, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
