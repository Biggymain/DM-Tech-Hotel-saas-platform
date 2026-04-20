<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MonnifyWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('monnify-signature');
        $hotelId = $request->input('eventData.metaData.hotel_id') ?? 1;

        $gateway = \App\Models\GatewaySetting::where('hotel_id', $hotelId)->where('gateway_name', 'monnify')->first();

        if (!$gateway) {
            return response()->json(['status' => 'error', 'message' => 'Gateway not configured'], 400);
        }

        $expectedSignature = hash_hmac('sha512', $payload, $gateway->secret_key);

        if ($expectedSignature !== $signature) {
            \App\Services\AuditLogService::log(
                'webhook_security',
                0,
                'webhook_spoofing',
                null,
                ['ip' => $request->ip(), 'payload' => $request->all()],
                'Potential Webhook Spoofing'
            );
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 403);
        }

        // Signature is valid. Update subscription expiry (e.g., +30 days) if payment successful.
        $eventData = $request->input('eventData');
        if ($eventData && isset($eventData['paymentStatus']) && $eventData['paymentStatus'] === 'PAID') {
            $subscription = \App\Models\HotelSubscription::where('hotel_id', $hotelId)->latest()->first();
            if ($subscription) {
                // Determine new expiry
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
        }

        return response()->json(['status' => 'success']);
    }
}
