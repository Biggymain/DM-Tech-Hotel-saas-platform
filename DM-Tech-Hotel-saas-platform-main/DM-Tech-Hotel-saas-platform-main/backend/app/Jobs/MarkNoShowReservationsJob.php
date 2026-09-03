<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Reservation;
use App\Services\FolioService;
use App\Services\AuditLogService;
use App\Services\ActivityLogService;
use App\Events\ReservationMarkedNoShow;
use Carbon\Carbon;

class MarkNoShowReservationsJob implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        //
    }

    public function handle(FolioService $folioService, AuditLogService $auditLogService, ActivityLogService $activityLogService): void
    {
        // Find confirmed reservations where check_in_date is today or earlier
        $reservations = Reservation::with(['hotel', 'rooms', 'folios'])->where('status', '=', 'confirmed')
            ->whereDate('check_in_date', '<=', today())
            ->get();

        /** @var Reservation $reservation */
        foreach ($reservations as $reservation) {
            $hotel = $reservation->hotel;
            $graceHours = $hotel->reservation_grace_hours ?? 0;
            // Default check-in time assumed to be 14:00 (2:00 PM) if purely date-based
            $noShowTime = Carbon::parse($reservation->check_in_date)->startOfDay()->addHours(14 + $graceHours);

            if (now()->isAfter($noShowTime)) {
                $this->processNoShow($reservation, $hotel, $folioService, $auditLogService, $activityLogService);
            }
        }
    }

    private function processNoShow(Reservation $reservation, $hotel, FolioService $folioService, AuditLogService $auditLogService, ActivityLogService $activityLogService)
    {
        $reservation->update(['status' => 'no_show']);

        $penaltyAmount = $this->calculatePenalty($reservation, $hotel);

        if ($penaltyAmount > 0) {
            $folio = $reservation->folios()->firstOrCreate([
                'hotel_id' => $hotel->id,
            ], ['status' => 'open']); // set status open if creating

            $folioService->addCharge(
                folio: $folio,
                description: "No-Show Penalty",
                amount: $penaltyAmount,
                attachableType: Reservation::class,
                attachableId: $reservation->id,
                isPenalty: true
            );
        }

        // Invalidate active room_qr_sessions linked to reservation
        if (Schema::hasTable('room_qr_sessions')) {
            $sessions = DB::table('room_qr_sessions')
                ->where('reservation_id', $reservation->id)
                ->where('is_active', true)
                ->get();

            foreach ($sessions as $session) {
                DB::table('room_qr_sessions')
                    ->where('id', $session->id)
                    ->update(['is_active' => false]);
                
                $activityLogService->logSystemEvent(
                    $hotel->id,
                    'QR Session Invalidated',
                    "QR Session {$session->id} for reservation {$reservation->reservation_number} invalidated due to No-Show",
                    'info',
                    ['reservation_id' => $reservation->id, 'session_id' => $session->id]
                );
            }
        }

        event(new ReservationMarkedNoShow($reservation));

        $auditLogService::log(
            entityType: 'Reservation',
            entityId: $reservation->id,
            changeType: 'Status Update',
            oldValues: ['status' => 'confirmed'],
            newValues: ['status' => 'no_show'],
            reason: 'Automated No-Show policy enforcement',
            source: 'job',
            hotelId: $hotel->id,
            userId: null
        );
    }

    private function calculatePenalty(Reservation $reservation, $hotel)
    {
        $type = $hotel->no_show_penalty_type;
        if ($type === 'deposit') {
            return $reservation->deposit_paid ? floatval($reservation->deposit_amount ?? 0) : 0;
        } elseif ($type === 'first_night') {
            $firstRoom = $reservation->rooms->first();
            return $firstRoom ? floatval($firstRoom->pivot->rate) : 0;
        } elseif ($type === 'full_stay') {
            return floatval($reservation->total_amount);
        }

        return 0;
    }
}
