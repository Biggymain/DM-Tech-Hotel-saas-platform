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
        $reservation = $this->reservation->fresh(['hotel', 'room', 'guest', 'folio']);

        if (!$reservation) {
            Log::warning("[GenerateDigitalKeyJob] Reservation #{$this->reservation->id} not found.");
            return;
        }

        // Idempotency check — don't generate a second key if one already exists and is active
        $existingKey = DigitalKey::where('reservation_id', $reservation->id)
            ->where('status', 'active')
            ->first();

        if ($existingKey) {
            Log::info("[GenerateDigitalKeyJob] Active key already exists for reservation #{$reservation->id}. Skipping.");
            $notifier->sendBookingConfirmation($reservation, $existingKey);
            return;
        }

        try {
            $key = $lockService->generateKey($reservation);
            Log::info("[GenerateDigitalKeyJob] Key #{$key->id} generated for reservation #{$reservation->id}.");
            $notifier->sendBookingConfirmation($reservation, $key);
        } catch (\Exception $e) {
            Log::error("[GenerateDigitalKeyJob] Failed for reservation #{$reservation->id}: {$e->getMessage()}");
            $this->fail($e);
        }
    }
}
