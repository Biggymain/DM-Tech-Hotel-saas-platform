<?php

namespace App\Events;

use App\Models\GuestServiceRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GuestServiceRequested
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $request;

    public function __construct(GuestServiceRequest $request)
    {
        $this->request = $request;
    }
}
