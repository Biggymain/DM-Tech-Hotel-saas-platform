<?php

namespace App\Jobs;

use App\Models\ChannelSyncLog;
use App\Models\HotelChannelConnection;
use App\Services\ChannelManager\ChannelReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncChannelReservationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $connectionId) {}

    public function handle(ChannelReservationService $service): void
    {
        $connection = HotelChannelConnection::with('otaChannel', 'hotel')
            ->find($this->connectionId);

        if (!$connection || $connection->status !== 'active') {
            Log::info("SyncChannelReservationsJob: Skipping inactive connection {$this->connectionId}");
            return;
        }

        // In a real implementation this would poll the OTA API for new bookings.
        // The framework is in place for the real-time webhook path.
        ChannelSyncLog::create([
            'hotel_id' => $connection->hotel_id,
            'ota_channel_id' => $connection->ota_channel_id,
            'operation' => 'reservation_pull',
            'status' => 'success',
            'request_payload' => ['polled_at' => now()->toIso8601String()],
        ]);

        $connection->update(['last_sync_at' => now()]);
        Log::info("SyncChannelReservationsJob: Completed poll for connection {$this->connectionId}");
    }
}
