<?php

namespace App\Services;

use App\Jobs\SyncChannelAvailabilityJob;
use App\Jobs\SyncChannelPricingJob;
use App\Models\ChannelIntegration;
use App\Models\RatePlan;
use App\Models\RoomType;
use Illuminate\Support\Facades\Log;

class ChannelManagerService
{
    private OTAReservationImportService $importService;

    public function __construct(OTAReservationImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Dispatch availability sync to channels
     */
    public function syncAvailability(RoomType $roomType)
    {
        // Background Job dispatcher
        Log::info("Dispatching SyncChannelAvailabilityJob for RoomType: {$roomType->id}");
        dispatch(new SyncChannelAvailabilityJob($roomType));
    }

    /**
     * Dispatch pricing sync to channels
     */
    public function syncPricing(RoomType $roomType, RatePlan $ratePlan)
    {
        Log::info("Dispatching SyncChannelPricingJob for RoomType: {$roomType->id}, RatePlan: {$ratePlan->id}");
        dispatch(new SyncChannelPricingJob($roomType, $ratePlan));
    }

    /**
     * Import an incoming OTA reservation payload
     */
    public function importReservation(ChannelIntegration $integration, array $payload)
    {
        if (!$integration->is_active || !$integration->sync_enabled || !$integration->sync_reservations) {
            Log::info("Skipping reservation import for integration {$integration->id}: Sync disabled.");
            return null;
        }

        try {
            return $this->importService->importReservation($integration, $payload);
        } catch (\Exception $e) {
            // Log the critical failure securely so the GUI can expose it
            \App\Models\ChannelSyncLog::create([
                'hotel_id' => $integration->hotel_id,
                'channel_integration_id' => $integration->id,
                'sync_type' => 'reservation_import',
                'status' => 'failed',
                'request_payload' => $payload,
                'error_message' => $e->getMessage(),
                'synced_at' => now()
            ]);
            throw $e;
        }
    }
}
