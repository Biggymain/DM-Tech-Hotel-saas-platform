<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use Illuminate\Http\Request;
use App\Models\PaymentTransaction;
use App\Models\PaymentGateway;
use App\Models\ProcessedWebhook;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Concerns\HandlesWebhookSignatures;

class PaymentWebhookController extends Controller
{
    use HandlesWebhookSignatures;
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function handleWebhook(Request $request, string $gateway)
    {
        $payload = $request->all();
        $rawContent = $request->getContent();
        
        // ── HMAC SIGNATURE VERIFICATION ──────────────────────────────────────
        $verified = $this->verifySignature($request, $gateway, $rawContent);
        
        if (!$verified) {
            // Log to audit logs for security monitoring
            \App\Models\AuditLog::create([
                'hotel_id' => 0, // System-wide or unknown at this point
                'change_type' => 'security_alert',
                'entity_type' => 'payment_webhook',
                'entity_id' => 0,
                'source' => 'webhook_verification',
                'reason' => "Unauthorized webhook attempt from IP: " . $request->ip(),
                'new_values' => [
                    'gateway' => $gateway,
                    'headers' => $request->headers->all(),
                    'payload_snippet' => substr($rawContent, 0, 500)
                ]
            ]);

            return response()->json(['message' => 'Invalid Signature'], 401);
        }

        // Extract basic ID just for lookup, the actual parsing is done by driver later
        $gatewayTransactionId = $payload['data']['object']['id'] ?? $payload['data']['reference'] ?? $payload['data']['id'] ?? $payload['id'] ?? $payload['txRef'] ?? $payload['resource']['id'] ?? null;

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

        // Idempotency Gate
        if (ProcessedWebhook::where('provider_reference', $gatewayTransactionId)->exists()) {
            return response()->json(['message' => 'Webhook Processed']); // Idempotent 200 OK
        }

        if ($webhookData['status'] === 'captured' && in_array($transaction->status, ['pending', 'authorized'])) {
            DB::transaction(function () use ($gatewayTransactionId, $gateway, $webhookData, $transaction) {
                ProcessedWebhook::create([
                    'provider_reference' => $gatewayTransactionId,
                    'gateway' => $gateway,
                    'amount' => $transaction->amount ?? null,
                    'status' => 'captured',
                    'processed_at' => now(),
                ]);

                $transaction->update([
                    'status' => 'captured',
                    'processed_at' => now(),
                ]);
                event(new \App\Events\PaymentCompleted($transaction));
            });
        } elseif ($webhookData['status'] === 'failed') {
            DB::transaction(function () use ($gatewayTransactionId, $gateway, $webhookData, $transaction) {
                ProcessedWebhook::create([
                    'provider_reference' => $gatewayTransactionId,
                    'gateway' => $gateway,
                    'amount' => $transaction->amount ?? null,
                    'status' => 'failed',
                    'processed_at' => now(),
                ]);

                $transaction->update([
                    'status' => 'failed',
                    'processed_at' => now(),
                ]);
                event(new \App\Events\PaymentFailed($transaction));
            });
        }

        return response()->json(['message' => 'Webhook Processed']);
    }

    /**
     * Verify the HMAC signature of the incoming request.
     */
    private function verifySignature(Request $request, string $gateway, string $rawContent): bool
    {
        if ($gateway === 'paystack') {
            $secret = config('services.paystack.secret');
            
            if (empty($secret)) {
                \Illuminate\Support\Facades\Log::critical("PAYSTACK_SECRET_KEY is missing in .env. Webhook verification bypassed to prevent data loss.");
                return true; // Fail-safe: allow request but log critical error
            }

            $signature = $request->header('x-paystack-signature');
            return $this->verifyPaystackSignature($rawContent, $secret, trim($signature));
        }

        if ($gateway === 'flutterwave') {
            $secret = config('services.flutterwave.secret_hash');

            if (empty($secret)) {
                \Illuminate\Support\Facades\Log::critical("FLUTTERWAVE_SECRET_HASH is missing in .env. Webhook verification bypassed to prevent data loss.");
                return true; // Fail-safe
            }

            $signature = $request->header('verif-hash');
            return $this->verifyFlutterwaveSignature($secret, trim($signature));
        }

        // Add other gateways as needed (Stripe, PayPal usually have their own SDK verifiers)
        return true; 
    }
}
