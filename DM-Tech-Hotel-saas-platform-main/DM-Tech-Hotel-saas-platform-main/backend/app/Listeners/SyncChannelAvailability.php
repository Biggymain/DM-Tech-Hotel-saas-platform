<?php

namespace App\Listeners;

use App\Services\ChannelManagerService;

class SyncChannelAvailability
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
     * Matches ReservationCreated and ReservationCancelled
     *
     * @param  \App\Events\ReservationCreated|\App\Events\ReservationCancelled  $event
     * @return void
     */
    public function handle(\App\Events\ReservationCreated|\App\Events\ReservationCancelled $event)
    {
        if (isset($event->reservation) && $event->reservation->roomType) {
            $this->channelManager->syncAvailability($event->reservation->roomType);
        }
    }
}
