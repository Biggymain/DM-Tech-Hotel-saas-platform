<?php

namespace App\Jobs;

use App\Models\HotelChannelConnection;
use App\Services\ChannelManager\ChannelInventoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncChannelInventoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $connectionId) {}

    public function handle(ChannelInventoryService $service): void
    {
        $connection = HotelChannelConnection::with('otaChannel', 'hotel')
            ->find($this->connectionId);

        if (!$connection || $connection->status !== 'active') {
            Log::info("SyncChannelInventoryJob: Skipping inactive connection {$this->connectionId}");
            return;
        }

        $service->pushAvailability($connection);
        $service->pushRates($connection);

        Log::info("SyncChannelInventoryJob: Completed for connection {$this->connectionId}");
    }
}
