<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\ChannelIntegration;
use App\Services\ChannelManagerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChannelWebhookController extends Controller
{
    private ChannelManagerService $channelManager;

    public function __construct(ChannelManagerService $channelManager)
    {
        $this->channelManager = $channelManager;
    }

    /**
     * Handle incoming OTA webhooks
     */
    public function handle(Request $request, $channel_name)
    {
        // 1. Resolve Integration & Hotel Context
        // Webhooks often come without standard auth tokens, so we rely on the channel name
        // combined with a unique identifier in the payload or header.
        // Assuming channel passes hotel_identifier.
        $payload = $request->all();
        $hotelIdentifier = $payload['hotel_identifier'] ?? null;

        if (!$hotelIdentifier) {
            return response()->json(['error' => 'Missing hotel identifier'], 400);
        }

        // Bind for Tenantable Global Scopes
        app()->instance('tenant_id', $hotelIdentifier);

        // Ideally, we'd map this securely. Assuming hotel_identifier = hotel_id directly for now.
        $integration = ChannelIntegration::where('hotel_id', $hotelIdentifier)
            ->where('channel_name', $channel_name)
            ->first();

        if (!$integration) {
            return response()->json(['error' => 'Integration not found'], 404);
        }

        if (!$integration->is_active || !$integration->sync_enabled) {
             return response()->json(['status' => 'ignored', 'reason' => 'Sync disabled'], 200);
        }

        // 2. HMAC Signature Validation
        $signature = $request->header('X-Channel-Signature');
        
        if ($integration->webhook_secret) {
            if (!$signature) {
                return response()->json(['error' => 'Missing Signature'], 401);
            }

            // Standard HMAC SHA256 validation of the raw body
            $calculatedSignature = hash_hmac('sha256', $request->getContent(), $integration->webhook_secret);
            if (!hash_equals($calculatedSignature, $signature)) {
                return response()->json(['error' => 'Invalid Signature'], 401);
            }
        }

        // 3. Process Payload
        try {
            $type = $payload['event_type'] ?? 'reservation';
            
            if ($type === 'reservation') {
                $reservation = $this->channelManager->importReservation($integration, $payload);
                if ($reservation) {
                    return response()->json(['status' => 'success', 'reservation_id' => $reservation->id], 201);
                } else {
                    return response()->json(['status' => 'ignored', 'reason' => 'Already ingested or skipped'], 200);
                }
            }

            return response()->json(['status' => 'ignored', 'reason' => 'Unsupported event type'], 200);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Bad data from OTA
            return response()->json(['error' => 'Validation Failed', 'details' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error("Webhook processing failed: " . $e->getMessage());
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }
}
