<?php

namespace App\Services\ChannelManager;

use App\Models\HotelChannelConnection;
use App\Jobs\SyncChannelInventoryJob;
use App\Jobs\SyncChannelReservationsJob;
use Illuminate\Support\Facades\Log;

class ChannelSyncService
{
    public function __construct(
        private ChannelInventoryService $inventoryService,
        private ChannelReservationService $reservationService,
    ) {}

    /**
     * Trigger a full sync for all active connections of a hotel.
     */
    public function syncHotel(int $hotelId): void
    {
        $connections = HotelChannelConnection::where('hotel_id', $hotelId)
            ->where('status', 'active')
            ->with('otaChannel')
            ->get();

        foreach ($connections as $connection) {
            dispatch(new SyncChannelInventoryJob($connection->id));
            dispatch(new SyncChannelReservationsJob($connection->id));
        }

        Log::info("Dispatched OTA sync jobs for hotel {$hotelId}, connections: {$connections->count()}");
    }

    /**
     * Manually trigger inventory push for a specific connection.
     */
    public function pushInventory(HotelChannelConnection $connection): void
    {
        $this->inventoryService->pushAvailability($connection);
        $this->inventoryService->pushRates($connection);
    }

    /**
     * Get sync health status for all channels of a hotel.
     */
    public function getSyncStatus(int $hotelId): array
    {
        $connections = HotelChannelConnection::where('hotel_id', $hotelId)
            ->with('otaChannel')
            ->get();

        return $connections->map(function ($conn) {
            $lastLog = \App\Models\ChannelSyncLog::where('hotel_id', $conn->hotel_id)
                ->where('ota_channel_id', $conn->ota_channel_id)
                ->latest()
                ->first();

            return [
                'channel' => $conn->otaChannel->name,
                'provider' => $conn->otaChannel->provider,
                'status' => $conn->status,
                'last_sync_at' => $conn->last_sync_at?->diffForHumans(),
                'last_log_status' => $lastLog?->status,
                'last_log_operation' => $lastLog?->operation,
            ];
        })->toArray();
    }
}
