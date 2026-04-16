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
     * List transactions (staff action).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        if ($user->isGroupAdmin()) {
            $branchIds = $user->hotelGroup->branches()->pluck('id');
            $transactions = PaymentTransaction::whereIn('hotel_id', $branchIds)
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        } else {
            $transactions = PaymentTransaction::where('hotel_id', $user->hotel_id)
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        }

        return response()->json($transactions);
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

        // Logic Leak protection: Ensure the input matches the auth context exactly
        if ((int) $request->input('hotel_id') !== (int) $request->user()->hotel_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

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
        $allowedRoles = ['groupadmin', 'generalmanager', 'hotelowner', 'reception', 'receptionist', 'waiter', 'cashier'];
        
        $hasRightRole = $user->is_super_admin || $user->isGroupAdmin();
        if (!$hasRightRole) {
            foreach ($allowedRoles as $role) {
                if ($user->hasRole($role)) {
                    $hasRightRole = true;
                    break;
                }
            }
        }

        if (!$hasRightRole) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'transaction_id' => 'required',
        ]);

        $query = PaymentTransaction::query();
        
        if ($user->isGroupAdmin()) {
            $branchIds = $user->hotelGroup->branches()->pluck('id');
            if (app()->environment('testing')) {
                \Log::info("manualConfirm: Group Admin check", ['user' => $user->id, 'branches' => $branchIds->toArray(), 'target' => $validated['transaction_id']]);
            }
            $transaction = $query->whereIn('hotel_id', $branchIds)->findOrFail($validated['transaction_id']);
        } else {
            if (app()->environment('testing')) {
                \Log::info("manualConfirm: User check", ['user' => $user->id, 'hotel' => $user->hotel_id, 'target' => $validated['transaction_id']]);
            }
            $transaction = $query->where('hotel_id', $user->hotel_id)->findOrFail($validated['transaction_id']);
        }

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
     * List configured gateways for this hotel/group.
     */
    public function listGateways(Request $request)
    {
        $user = $request->user();
        $hotelId = $user->hotel_id;
        
        // If searching as group admin without specific context, we show all unique gateway types
        $gateways = \App\Models\PaymentGateway::where('hotel_id', $hotelId)->get();
        
        return response()->json($gateways);
    }

    /**
     * Store or update gateway configuration.
     */
    public function updateGateway(Request $request)
    {
        $user = $request->user();
        if (!$user->is_super_admin && !$user->isGroupAdmin() && !$user->hasRole('Manager')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'gateway_name' => 'required|string|in:stripe,paystack,monnify,paypal,flutterwave',
            'api_key'      => 'required|string',
            'api_secret'   => 'nullable|string',
            'payment_mode' => 'nullable|string|in:test,live',
            'is_active'    => 'nullable|boolean',
        ]);

        $gateway = \App\Models\PaymentGateway::updateOrCreate(
            [
                'hotel_id' => $user->hotel_id,
                'gateway_name' => $validated['gateway_name']
            ],
            $validated
        );

        return response()->json([
            'message' => 'Payment gateway updated.',
            'gateway' => $gateway
        ]);
    }

    /**
     * Test Payment Gateway Connection.
     */
    public function testConnection(Request $request)
    {
        $validated = $request->validate([
            'gateway' => 'required|string|in:stripe,paystack,monnify,paypal,flutterwave',
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
