<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GuestFolioUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $reservationId;

    public function __construct(int $reservationId)
    {
        $this->reservationId = $reservationId;
    }
}
