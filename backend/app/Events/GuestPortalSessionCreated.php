<?php

namespace App\Events;

use App\Models\GuestPortalSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GuestPortalSessionCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $session;

    public function __construct(GuestPortalSession $session)
    {
        $this->session = $session;
    }
}
