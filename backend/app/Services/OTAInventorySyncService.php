<?php

namespace App\Services;

use App\Models\ChannelIntegration;
use App\Models\ChannelSyncLog;
use App\Models\RatePlan;
use App\Models\RoomType;
use Illuminate\Support\Facades\Log;

class OTAInventorySyncService
{
    /**
     * Push availability to connected channels
     */
    public function syncAvailability(RoomType $roomType)
    {
        $integrations = ChannelIntegration::where('hotel_id', $roomType->hotel_id)
            ->where('is_active', true)
            ->where('sync_enabled', true)
            ->where('sync_inventory', true)
            ->with(['roomMappings' => function ($query) use ($roomType) {
                $query->where('room_type_id', $roomType->id);
            }])
            ->get();

        foreach ($integrations as $integration) {
            if ($integration->roomMappings->isEmpty()) {
                continue; // Room is not mapped for this channel
            }

            $mapping = $integration->roomMappings->first();
            
            // In a real app, this calculates availability based on current reservations
            // For now, we simulate an API call payload
            $availableRooms = $roomType->rooms()->where('status', 'available')->count();

            $payload = [
                'channel_room_id' => $mapping->channel_room_identifier,
                'available_count' => $availableRooms,
                'date' => now()->toDateString()
            ];

            // Mocking the external API call success
            $success = true;
            $responsePayload = ['status' => 'acknowledged', 'updated_count' => $availableRooms];
            $errorMessage = null;

            $this->logSync($integration, 'availability', $success, $payload, $responsePayload, $errorMessage);
            
            if ($success) {
                $integration->update(['last_sync_at' => now()]);
            }
        }
    }

    /**
     * Push pricing to connected channels
     */
    public function syncPricing(RoomType $roomType, RatePlan $ratePlan)
    {
        $integrations = ChannelIntegration::where('hotel_id', $roomType->hotel_id)
            ->where('is_active', true)
            ->where('sync_enabled', true)
            ->where('sync_pricing', true)
            ->with([
                'roomMappings' => fn($q) => $q->where('room_type_id', $roomType->id),
                'rateMappings' => fn($q) => $q->where('rate_plan_id', $ratePlan->id)
            ])
            ->get();

        foreach ($integrations as $integration) {
            if ($integration->roomMappings->isEmpty() || $integration->rateMappings->isEmpty()) {
                continue; // Cannot push price if either room or rate plan isn't mapped
            }

            $roomMapping = $integration->roomMappings->first();
            $rateMapping = $integration->rateMappings->first();

            // Rely on the robust PricingService for real-time calculation
            /** @var PricingService $pricingService */
            $pricingService = app(PricingService::class);
            
            // Assume we are syncing for today for simplicity in this method, 
            // real-world OTA syncs batch future dates
            $calculatedPrice = $pricingService->calculateRoomPrice($roomType, now(), $ratePlan->id);

            $payload = [
                'channel_room_id' => $roomMapping->channel_room_identifier,
                'channel_rate_id' => $rateMapping->channel_rate_identifier,
                'amount' => $calculatedPrice,
                'currency' => $roomType->hotel->currency->code ?? 'USD'
            ];

            // Mocking external API call
            $success = true;
            $responsePayload = ['status' => 'price_updated'];
            $errorMessage = null;

            $this->logSync($integration, 'pricing', $success, $payload, $responsePayload, $errorMessage);
            
            if ($success) {
                $integration->update(['last_sync_at' => now()]);
            }
        }
    }

    private function logSync(ChannelIntegration $integration, string $type, bool $success, array $request, ?array $response, ?string $error)
    {
        ChannelSyncLog::create([
            'hotel_id' => $integration->hotel_id,
            'channel_integration_id' => $integration->id,
            'sync_type' => $type,
            'status' => $success ? 'success' : 'failed',
            'request_payload' => $request,
            'response_payload' => $response,
            'error_message' => $error,
            'synced_at' => now()
        ]);
        
        // Also log broadly internally
        if (!$success) {
            Log::error("OTA Sync Failed [{$type}] for integration {$integration->id}: {$error}");
        }
    }
}
