<?php

namespace App\Listeners;

use App\Events\GuestCheckedOut;
use App\Models\GuestPortalSession;

class TerminateGuestPortalSessions
{
    /**
     * Handle the event.
     */
    public function handle(GuestCheckedOut $event): void
    {
        GuestPortalSession::where('reservation_id', $event->reservation->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'expires_at' => now(),
            ]);
    }
}
