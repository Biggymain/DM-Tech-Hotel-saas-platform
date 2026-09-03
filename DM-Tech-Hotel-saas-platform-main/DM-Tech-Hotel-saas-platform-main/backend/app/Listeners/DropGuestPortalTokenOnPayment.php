<?php

namespace App\Listeners;

use App\Events\PaymentCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DropGuestPortalTokenOnPayment
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    public function handle(PaymentCompleted $event): void
    {
        $transaction = $event->transaction;

        if ($transaction && $transaction->guest_id) {
            // Destroy the session token instantly upon successful payment
            \App\Models\GuestPortalSession::where('guest_id', $transaction->guest_id)->delete();

            // Notify the Table Session logic that this specific guest has cleared their tab
            \App\Models\TableSessionGuest::where('guest_id', $transaction->guest_id)
                ->where('has_paid', false)
                ->update(['has_paid' => true]);
        }
    }
}
