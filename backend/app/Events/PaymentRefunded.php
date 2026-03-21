<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\PaymentTransaction;

class PaymentRefunded
{
    use Dispatchable, SerializesModels;

    public $transaction;
    public $refundAmount;

    public function __construct(PaymentTransaction $transaction, float $refundAmount)
    {
        $this->transaction = $transaction;
        $this->refundAmount = $refundAmount;
    }
}
