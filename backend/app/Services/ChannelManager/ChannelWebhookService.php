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
        $hotelId = $payload['hotel_id'] ?? null;

        if (!$hotelId) {
            abort(422, 'Missing hotel_id in webhook payload.');
        }

        $connection = HotelChannelConnection::where('hotel_id', $hotelId)
            ->where('ota_channel_id', $channel->id)
            ->where('status', 'active')
            ->firstOrFail();

        // Bind the tenant into the service container so Tenantable model scopes work
        // for this unauthenticated webhook request (same binding TenantIsolationMiddleware sets for logged-in users)
        app()->instance('tenant_id', $hotelId);

        $eventType = $payload['event_type'] ?? 'reservation_created';

        ChannelSyncLog::create([
            'hotel_id' => $hotelId,
            'ota_channel_id' => $channel->id,
            'operation' => 'webhook',
            'status' => 'received',
            'request_payload' => $payload,
        ]);

        if (in_array($eventType, ['reservation_created', 'booking_new'])) {
            $reservation = $this->reservationService->importReservation($connection, $payload);
            return ['status' => 'imported', 'reservation_id' => $reservation?->id];
        }

        return ['status' => 'acknowledged', 'event' => $eventType];
    }

    private function verifySignature(OtaChannel $channel, Request $request): bool
    {
        // Provider-specific signature validation
        // For now, always pass (in production each OTA has its own HMAC scheme)
        $signature = $request->header('X-Webhook-Signature');
        if (!$signature) {
            // Allow unsigned in local/staging environments
            return app()->environment('local', 'testing') ? true : false;
        }
        return true;
    }
}
