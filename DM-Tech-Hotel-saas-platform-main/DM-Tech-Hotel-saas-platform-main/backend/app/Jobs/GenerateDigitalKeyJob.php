<?php

namespace App\Jobs;

use App\Models\DigitalKey;
use App\Models\Reservation;
use App\Services\DoorLockService;
use App\Services\GuestNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * GenerateDigitalKeyJob
 *
 * Dispatched when a Reservation transitions to 'checked_in'.
 * Handled asynchronously via the Redis queue so the web request
 * is never blocked by potentially slow door lock API calls.
 */
class GenerateDigitalKeyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30; // Seconds between retries

    public function __construct(public readonly Reservation $reservation) {}

    public function handle(DoorLockService $lockService, GuestNotificationService $notifier): void
    {
        try {
            $keys = $lockService->generateKeys($this->reservation);
            foreach ($keys as $key) {
                Log::info("[GenerateDigitalKeyJob] Key #{$key->id} generated for reservation #{$this->reservation->id}.");
                $notifier->sendBookingConfirmation($this->reservation, $key);
            }
        } catch (\Exception $e) {
            Log::error("[GenerateDigitalKeyJob] Failed for reservation #{$this->reservation->id}: {$e->getMessage()}");
            $this->fail($e);
        }
    }
}
