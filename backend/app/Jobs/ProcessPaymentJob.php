<?php

namespace App\Jobs;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'high';
    public $tries = 3;

    public function __construct(
        public int $invoiceId,
        public float $amount,
        public int $paymentMethodId,
        public int $userId,
        public ?string $reference = null
    ) {}

    public function handle(\App\Services\BillingService $billingService): void
    {
        $invoice = \App\Models\Invoice::findOrFail($this->invoiceId);

        // 1. Idempotency: Check if this reference was already processed
        if ($this->reference && \App\Models\Payment::where('transaction_reference', $this->reference)->exists()) {
            return;
        }

        // 2. Process via Billing Service
        $billingService->processPayment(
            $invoice,
            $this->amount,
            $this->paymentMethodId,
            $this->userId,
            $this->reference
        );
        
        Log::info("Payment for Invoice {$this->invoiceId} processed on high-priority queue.");
    }
}
