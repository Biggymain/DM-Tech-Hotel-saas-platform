<?php

namespace App\Listeners;

use App\Events\RoomPriceCalculated;
use App\Services\ChannelManagerService;

class SyncChannelPricing
{
    private ChannelManagerService $channelManager;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(ChannelManagerService $channelManager)
    {
        $this->channelManager = $channelManager;
    }

    /**
     * Handle the event.
     *
     * @param  \App\Events\RoomPriceCalculated  $event
     * @return void
     */
    public function handle(RoomPriceCalculated $event)
    {
        if ($event->roomType && $event->ratePlan) {
            $this->channelManager->syncPricing($event->roomType, $event->ratePlan);
        }
    }
}
