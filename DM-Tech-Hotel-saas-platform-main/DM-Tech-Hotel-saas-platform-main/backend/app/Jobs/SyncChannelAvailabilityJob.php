<?php

namespace App\Jobs;

use App\Models\RoomType;
use App\Services\OTAInventorySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncChannelAvailabilityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $roomType;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(RoomType $roomType)
    {
        $this->roomType = $roomType;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(OTAInventorySyncService $otaSyncService)
    {
        $otaSyncService->syncAvailability($this->roomType);
    }
}
