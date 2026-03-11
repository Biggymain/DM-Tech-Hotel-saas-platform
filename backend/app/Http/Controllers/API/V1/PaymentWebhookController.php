<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PaymentTransaction;
use App\Models\PaymentGateway;
use App\Services\PaymentService;

class PaymentWebhookController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function handleWebhook(Request $request, string $gateway)
    {
        $payload = $request->all();
        // Extract basic ID just for lookup, the actual parsing is done by driver later
        $gatewayTransactionId = $payload['data']['object']['id'] ?? $payload['id'] ?? $payload['eventData']['transactionReference'] ?? $payload['resource']['id'] ?? null;

        if (!$gatewayTransactionId) {
            return response()->json(['message' => 'Invalid payload format'], 400);
        }

        $transaction = PaymentTransaction::where('gateway_transaction_id', $gatewayTransactionId)
            ->where('payment_gateway', $gateway)
            ->first();

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        $gatewayDriver = $this->paymentService->resolveGateway($transaction->hotel_id, $gateway);
        $webhookData = $gatewayDriver->handleWebhook($payload);

        if (in_array($transaction->status, ['captured', 'failed', 'refunded'])) {
            return response()->json(['message' => 'Duplicate webhook transaction rejected', 'status' => 'duplicate'], 200);
        }

        if ($webhookData['status'] === 'captured' && in_array($transaction->status, ['pending', 'authorized'])) {
            try {
                $transaction->update([
                    'status' => 'captured',
                    'processed_at' => now(),
                ]);
                event(new \App\Events\PaymentCompleted($transaction));
            } catch (\Illuminate\Database\QueryException $e) {
                // Catch unique constraint if any (though we are updating, not inserting)
                return response()->json(['message' => 'Duplicate transaction caught'], 409);
            }
        } elseif ($webhookData['status'] === 'failed') {
            $transaction->update([
                'status' => 'failed',
                'processed_at' => now(),
            ]);
            event(new \App\Events\PaymentFailed($transaction));
        }

        return response()->json(['message' => 'Webhook Processed']);
    }
}
