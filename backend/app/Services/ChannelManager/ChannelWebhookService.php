<?php

namespace App\Services\ChannelManager;

use App\Models\ChannelSyncLog;
use App\Models\HotelChannelConnection;
use App\Models\OtaChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChannelWebhookService
{
    public function __construct(private ChannelReservationService $reservationService) {}

    /**
     * Handle incoming webhook from an OTA channel (identified by provider slug).
     */
    public function handle(string $channelProvider, Request $request): array
    {
        $channel = OtaChannel::where('provider', $channelProvider)->where('is_active', true)->firstOrFail();

        // Validate signature
        if (!$this->verifySignature($channel, $request)) {
            Log::warning("Invalid webhook signature for channel: {$channelProvider}");
            abort(401, 'Invalid webhook signature.');
        }

        $payload = $request->all();
        if (empty($payload)) {
            $payload = json_decode($request->getContent(), true) ?? [];
        }
        $hotelId = $payload['hotel_id'] ?? ($payload['hotel_identifier'] ?? null);

        if (!$hotelId) {
            abort(422, 'Missing hotel_id in webhook payload.');
        }

        $connection = HotelChannelConnection::where('hotel_id', $hotelId)
            ->where('ota_channel_id', $channel->id)
            ->first();

        if (!$connection || $connection->status !== 'active') {
             return ['status' => 'ignored', 'reason' => 'Sync disabled'];
        }

        // Bind the tenant into the service container so Tenantable model scopes work
        // for this unauthenticated webhook request (same binding TenantIsolationMiddleware sets for logged-in users)
        app()->instance('tenant_id', $hotelId);

        $eventType = $payload['event_type'] ?? null;

        ChannelSyncLog::create([
            'hotel_id' => $hotelId,
            'ota_channel_id' => $channel->id,
            'operation' => 'webhook',
            'status' => 'received',
            'request_payload' => $payload,
        ]);

        if (in_array($eventType, ['reservation_created', 'booking_new'])) {
            $externalId = $payload['external_reservation_id'] ?? ($payload['channel_reservation_id'] ?? null);
            if (!$externalId) {
                return ['status' => 'ignored', 'reason' => 'Missing OTA reservation ID'];
            }
            $reservation = $this->reservationService->importReservation($connection, $payload);
            
            if (!$reservation) {
                return ['status' => 'ignored', 'reason' => 'Already ingested or skipped'];
            }
            
            return ['status' => 'imported', 'reservation_id' => $reservation->id];
        }

        return ['status' => 'acknowledged', 'event' => $eventType];
    }

    private function verifySignature(OtaChannel $channel, Request $request): bool
    {
        $signature = $request->header('X-Webhook-Signature') ?? $request->header('X-Channel-Signature');
        if (!$signature) {
             return app()->environment('local', 'testing') ? true : false;
        }

        $payload = $request->all();
        if (empty($payload)) {
            $payload = json_decode($request->getContent(), true) ?? [];
        }
        $hotelId = $payload['hotel_id'] ?? ($payload['hotel_identifier'] ?? null);
        if (!$hotelId) return false;

        $connection = HotelChannelConnection::where('hotel_id', $hotelId)
            ->where('ota_channel_id', $channel->id)
            ->first();

        $secret = $connection?->api_secret;
        
        // Fallback to legacy ChannelIntegration if needed for tests
        if (!$secret) {
            $integration = \App\Models\ChannelIntegration::where('hotel_id', $hotelId)
                ->where('channel_name', $channel->provider)
                ->first();
            $secret = $integration?->webhook_secret;
        }

        if (!$secret) {
            return app()->environment('local', 'testing');
        }

        $calculatedSignature = hash_hmac('sha256', $request->getContent(), $secret);
        return hash_equals($calculatedSignature, $signature);
    }
}
