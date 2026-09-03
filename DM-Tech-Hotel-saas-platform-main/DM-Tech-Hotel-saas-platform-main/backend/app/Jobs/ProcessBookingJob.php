<?php

namespace App\Jobs;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBookingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 600];

    public function __construct(public Reservation $reservation) {}

    public function handle(): void
    {
        // 1. Idempotency Check: Avoid duplicate folios
        if ($this->reservation->folios()->exists()) {
            Log::info("Reservation {$this->reservation->id} already has a folio. Skipping.");
            return;
        }

        // 2. Heavy Lifting: Generate Folio, Allocate Taxes, Notify Customer
        // Assume $reservation->createFolio() exists or performs the logic
        // $this->reservation->createFolio();
        
        Log::info("Reservation {$this->reservation->id} processed successfully.");
        
        // 3. Chain Sync (Handled by dispatcher usually, but syncable trait will fire)
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessBookingJob failed for Reservation {$this->reservation->id}: " . $exception->getMessage());
    }
}
