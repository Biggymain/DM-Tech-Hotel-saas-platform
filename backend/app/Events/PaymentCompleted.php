<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\PaymentTransaction;

class PaymentCompleted
{
    use Dispatchable, SerializesModels;

    public $transaction;

    public function __construct(PaymentTransaction $transaction)
    {
        $this->transaction = $transaction;
    }
}
