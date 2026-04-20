<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use App\Models\ProcessedWebhook;
use App\Http\Controllers\Concerns\HandlesWebhookSignatures;

class MonnifyWebhookController extends Controller
{
    use HandlesWebhookSignatures;
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('monnify-signature');
        $hotelId = $request->input('eventData.metaData.hotel_id') ?? 1;

        $gateway = \App\Models\GatewaySetting::where('hotel_id', $hotelId)->where('gateway_name', 'monnify')->first();

        if (!$gateway) {
            return response()->json(['status' => 'error', 'message' => 'Gateway not configured'], 400);
        }

        if (!$this->verifyMonnifySignature($payload, $gateway->secret_key, $signature)) {
            \App\Services\AuditLogService::log(
                'webhook_security', 0, 'webhook_spoofing', null,
                ['ip' => $request->ip(), 'payload' => $request->all()],
                'Potential Webhook Spoofing'
            );
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 403);
        }

        $eventData = $request->input('eventData');
        $transactionReference = $eventData['transactionReference'] ?? $eventData['paymentReference'] ?? null;
        $paymentStatus = $eventData['paymentStatus'] ?? null;
        $amount = $eventData['amountPaid'] ?? null;

        if (!$transactionReference) {
            return response()->json(['status' => 'error', 'message' => 'Invalid payload format'], 400);
        }

        // Idempotency Gate
        if (ProcessedWebhook::where('provider_reference', $transactionReference)->exists()) {
            return response()->json(['status' => 'success']); // Idempotent 200 OK
        }

        if ($paymentStatus === 'PAID') {
            DB::transaction(function () use ($hotelId, $transactionReference, $amount, $paymentStatus) {
                ProcessedWebhook::create([
                    'provider_reference' => $transactionReference,
                    'gateway' => 'monnify',
                    'amount' => $amount,
                    'status' => $paymentStatus,
                    'processed_at' => now(),
                ]);

                $subscription = \App\Models\HotelSubscription::where('hotel_id', $hotelId)->latest()->first();
                if ($subscription) {
                    $newExpiry = now()->addDays(30);
                    if ($subscription->current_period_end && $subscription->current_period_end->isFuture()) {
                        $newExpiry = $subscription->current_period_end->addDays(30);
                    }
                    
                    $subscription->update([
                        'status' => 'active',
                        'current_period_end' => $newExpiry,
                        'grace_period_ends_at' => null
                    ]);
                }
            });
        } else {
            // Log failed webhooks unconditionally for audit trails
            ProcessedWebhook::create([
                'provider_reference' => $transactionReference,
                'gateway' => 'monnify',
                'amount' => $amount,
                'status' => $paymentStatus ?? 'UNKNOWN',
                'processed_at' => now(),
            ]);
        }

        return response()->json(['status' => 'success']);
    }
}
