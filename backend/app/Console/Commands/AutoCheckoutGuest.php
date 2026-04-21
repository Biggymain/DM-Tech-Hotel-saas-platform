<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use App\Services\CheckOutService;
use App\Events\RoomMarkedDirty;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoCheckoutGuest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reservations:auto-checkout';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automate guest check-out for reservations ending today with zero balance.';

    /**
     * Execute the console command.
     */
    public function handle(CheckOutService $checkOutService)
    {
        $today = now()->toDateString();
        
        $reservations = Reservation::where('check_out_date', '>=', $today . ' 00:00:00')
            ->where('check_out_date', '<=', $today . ' 23:59:59')
            ->where('status', 'checked_in')
            ->get();

        $this->info("Found {$reservations->count()} reservations pending check-out today.");

        foreach ($reservations as $reservation) {
            /** @var Reservation $reservation */
            $totalBalance = $reservation->folios()->sum('balance');

            if (abs(round($totalBalance, 2)) == 0) {
                try {
                    $checkOutService->checkOutGuest($reservation);
                    
                    // Fire Housekeeping alerts specifically
        foreach ($reservation->rooms as $room) {
                        /** @var \App\Models\Room $room */
                        event(new RoomMarkedDirty($room));
                    }

                    $this->info("Auto-checked out Reservation #{$reservation->id}");
                } catch (\Exception $e) {
                    $this->error("Failed to auto-checkout Reservation #{$reservation->id}: " . $e->getMessage());
                    $this->logRevenueRisk($reservation, $e->getMessage());
                }
            } else {
                $this->warn("Reservation #{$reservation->id} has outstanding balance: {$totalBalance}. Skipping auto-checkout.");
                $this->logRevenueRisk($reservation, "Outstanding balance: {$totalBalance}");
            }
        }
    }

    protected function logRevenueRisk(Reservation $reservation, string $reason)
    {
        AuditLogService::log(
            entityType: 'reservation',
            entityId: $reservation->id,
            changeType: 'auto_checkout_blocked',
            oldValues: ['status' => $reservation->status],
            newValues: ['status' => $reservation->status],
            reason: $reason,
            source: 'system',
            hotelId: $reservation->hotel_id
        );

        // Update the last audit log with Severity 10 specifically as required
        AuditLog::where('entity_id', $reservation->id)
            ->where('change_type', 'auto_checkout_blocked')
            ->orderBy('created_at', 'desc')
            ->first()
            ?->update(['severity_score' => 10]);
    }
}
