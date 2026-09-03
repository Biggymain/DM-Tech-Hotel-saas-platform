<?php

namespace App\Listeners;

use App\Events\OrderServed;
use App\Services\BillingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class GenerateInvoice
{
    protected BillingService $billingService;

    /**
     * Create the event listener.
     */
    public function __construct(BillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    /**
     * Handle the event.
     */
    public function handle(OrderServed $event): void
    {
        // Offload invoice generation immediately as order hits 'served'
        $this->billingService->generateInvoiceFromOrder($event->order);
    }
}
