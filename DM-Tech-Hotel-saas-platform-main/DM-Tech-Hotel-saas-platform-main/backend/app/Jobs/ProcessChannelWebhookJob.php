<?php

namespace App\Jobs;

use App\Models\HotelChannelConnection;
use App\Models\OtaChannel;
use App\Services\ChannelManager\ChannelReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessChannelWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $connectionId,
        private array $payload
    ) {}

    public function handle(ChannelReservationService $service): void
    {
        $connection = HotelChannelConnection::with('otaChannel')->find($this->connectionId);

        if (!$connection) {
            Log::error("ProcessChannelWebhookJob: Connection {$this->connectionId} not found.");
            return;
        }

        try {
            $eventType = $this->payload['event_type'] ?? 'reservation_created';

            if (in_array($eventType, ['reservation_created', 'booking_new'])) {
                $reservation = $service->importReservation($connection, $this->payload);
                Log::info("ProcessChannelWebhookJob: Imported reservation {$reservation?->id}");
            }
        } catch (\Exception $e) {
            Log::error("ProcessChannelWebhookJob failed: " . $e->getMessage());
            throw $e; // Re-throw so Laravel queues can retry
        }
    }
}
