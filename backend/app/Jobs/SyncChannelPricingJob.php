<?php

namespace App\Jobs;

use App\Models\RatePlan;
use App\Models\RoomType;
use App\Services\OTAInventorySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncChannelPricingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $roomType;
    public $ratePlan;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(RoomType $roomType, RatePlan $ratePlan)
    {
        $this->roomType = $roomType;
        $this->ratePlan = $ratePlan;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(OTAInventorySyncService $otaSyncService)
    {
        $otaSyncService->syncPricing($this->roomType, $this->ratePlan);
    }
}
