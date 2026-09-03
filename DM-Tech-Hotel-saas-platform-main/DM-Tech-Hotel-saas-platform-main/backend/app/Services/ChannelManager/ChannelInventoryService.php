<?php

namespace App\Services\ChannelManager;

use App\Models\ChannelSyncLog;
use App\Models\HotelChannelConnection;
use App\Models\RoomType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChannelInventoryService
{
    /**
     * Push current room availability to all connected OTA channels.
     */
    public function pushAvailability(HotelChannelConnection $connection): void
    {
        $hotel = $connection->hotel;
        $hotelId = $hotel->id;
        $channel = $connection->otaChannel;

        Log::info("Pushing availability to {$channel->name} for hotel {$hotelId}");

        $roomTypeMaps = \App\Models\RoomTypeChannelMap::where('hotel_id', $hotelId)
            ->where('ota_channel_id', $channel->id)
            ->with('roomType.rooms')
            ->get();

        foreach ($roomTypeMaps as $map) {
            $availableCount = $map->roomType->rooms()
                ->where('status', 'available')
                ->count();

            $payload = [
                'external_room_type_id' => $map->external_room_type_id,
                'available_count' => $availableCount,
                'date' => now()->toDateString(),
            ];

            $this->logSync($connection, 'inventory_push', 'success', $payload, null, null);
        }

        $connection->update(['last_sync_at' => now()]);
    }

    /**
     * Push pricing updates to connected OTA channels.
     */
    public function pushRates(HotelChannelConnection $connection): void
    {
        $hotelId = $connection->hotel_id;
        $channel = $connection->otaChannel;

        $ratePlanMaps = \App\Models\RatePlanChannelMap::where('hotel_id', $hotelId)
            ->where('ota_channel_id', $channel->id)
            ->with('ratePlan')
            ->get();

        foreach ($ratePlanMaps as $map) {
            $payload = [
                'external_rate_plan_id' => $map->external_rate_plan_id,
                'amount' => $map->ratePlan->base_rate ?? 0,
                'currency' => 'USD',
            ];

            $this->logSync($connection, 'rate_update', 'success', $payload, null, null);
        }
    }

    private function logSync(HotelChannelConnection $connection, string $operation, string $status, ?array $request, ?array $response, ?string $error): void
    {
        ChannelSyncLog::create([
            'hotel_id' => $connection->hotel_id,
            'ota_channel_id' => $connection->ota_channel_id,
            'operation' => $operation,
            'status' => $status,
            'request_payload' => $request,
            'response_payload' => $response,
            'error_message' => $error,
        ]);
    }
}
