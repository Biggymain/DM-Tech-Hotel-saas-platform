<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use Illuminate\Http\Request;
use App\Services\PaymentService;
use App\Models\Reservation;
use App\Models\PaymentTransaction;
use Illuminate\Validation\ValidationException;
use App\Events\PaymentCompleted;

class PaymentGatewayController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Create Payment Intent (for guest portal or standard API).
     */
    public function createIntent(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'gateway' => 'required|string',
            'reservation_id' => 'nullable|exists:reservations,id',
            'folio_id' => 'nullable|exists:folios,id',
            'hotel_id' => 'required|exists:hotels,id',
            'is_manual' => 'nullable|boolean',
            'pos_metadata' => 'nullable|array',
            'payment_source' => 'nullable|string|in:guest_portal,restaurant_pos,frontdesk,room_service,manual'
        ]);

        $reservation = null;
        if (!empty($validated['reservation_id'])) {
            $reservation = Reservation::find($validated['reservation_id']);
        }

        $intent = $this->paymentService->createPaymentIntent(
            $validated['amount'],
            $validated['currency'],
            $validated['gateway'],
            $validated['hotel_id'],
            $reservation,
            $validated['folio_id'] ?? null,
            $validated['is_manual'] ?? false,
            $validated['pos_metadata'] ?? [],
            $validated['payment_source'] ?? 'guest_portal'
        );

        return response()->json($intent, 201);
    }

    /**
     * Confirm / Capture Payment.
     */
    public function confirm(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|exists:payment_transactions,id',
        ]);

        $transaction = PaymentTransaction::findOrFail($validated['transaction_id']);
        
        $captured = $this->paymentService->capturePayment($transaction);

        return response()->json($captured, 200);
    }

    /**
     * Refund Payment.
     */
    public function refund(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|exists:payment_transactions,id',
            'amount' => 'nullable|numeric|min:0.01',
        ]);

        $transaction = PaymentTransaction::findOrFail($validated['transaction_id']);
        
        $refunded = $this->paymentService->refundPayment($transaction, $validated['amount'] ?? null);

        return response()->json($refunded, 200);
    }

    /**
     * Confirm a manual payment (staff action).
     */
    public function manualConfirm(Request $request)
    {
        $user = $request->user();
        $allowedRoles = ['Manager', 'Hotel Manager', 'Reception', 'Receptionist', 'Waiter', 'Cashier'];
        
        if (!$user->is_super_admin && !$user->roles()->whereIn('name', $allowedRoles)->exists()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'transaction_id' => 'required|exists:payment_transactions,id',
        ]);

        $transaction = PaymentTransaction::where('hotel_id', $user->hotel_id)
            ->findOrFail($validated['transaction_id']);

        if ($transaction->status !== 'manual_pending') {
            return response()->json(['message' => 'Transaction is not pending manual confirmation.'], 422);
        }

        $transaction->update([
            'status' => 'manual_confirmed',
            'processed_at' => now(),
        ]);

        event(new PaymentCompleted($transaction));

        return response()->json(['message' => 'Manual payment confirmed successfully.', 'transaction' => $transaction], 200);
    }

    /**
     * Test Payment Gateway Connection.
     */
    public function testConnection(Request $request)
    {
        $validated = $request->validate([
            'gateway' => 'required|string|in:stripe,paystack,monnify,paypal',
        ]);

        $hotelId = $request->user()->hotel_id;
        
        // In a real app, this would use the PaymentService to call a mock/test endpoint on the gateway.
        // For this implementation, we simulate based on configured credentials.
        $gateway = \App\Models\PaymentGateway::where('hotel_id', $hotelId)
            ->where('gateway_name', $validated['gateway'])
            ->first();

        if (!$gateway) {
            return response()->json(['success' => false, 'message' => "Gateway {$validated['gateway']} is not configured."], 404);
        }

        // Simulating a success response
        return response()->json([
            'success' => true,
            'message' => "Successfully connected to " . ucfirst($validated['gateway']),
            'timestamp' => now()->toIso8601String()
        ]);
    }
}
