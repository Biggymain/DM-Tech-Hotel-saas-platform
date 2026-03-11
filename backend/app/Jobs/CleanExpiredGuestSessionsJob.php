<?php

namespace App\Jobs;

use App\Models\GuestPortalSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CleanExpiredGuestSessionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        GuestPortalSession::where('is_active', true)
            ->where('expires_at', '<', now())
            ->update(['is_active' => false]);
    }
}
