<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Folio;
use App\Models\Room;
use App\Events\GuestCheckedOut;
use App\Events\FolioClosed;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckOutService
{
    /**
     * Complete the CheckOut workflow for a checked_in reservation.
     */
    public function checkOutGuest(Reservation $reservation)
    {
        if ($reservation->status !== 'checked_in') {
            throw ValidationException::withMessages([
                'status' => 'Only checked_in reservations can be checked out.'
            ]);
        }

        return DB::transaction(function () use ($reservation) {
            
            // Validate Folios
            foreach ($reservation->folios as $folio) {
                if ($folio->balance != 0) {
                     throw ValidationException::withMessages([
                         'folio' => "Cannot check out. Folio out of balance: {$folio->balance} {$folio->currency}"
                     ]);
                }

                if ($folio->status !== 'closed') {
                     $folio->update(['status' => 'closed']);
                     event(new FolioClosed($folio));
                }
            }

            // Update Reservation Status
            $reservation->update(['status' => 'checked_out']);

            // Update attached rooms status to dirty for housekeeping
            foreach ($reservation->rooms as $room) {
                $room->update([
                    'status' => 'available',
                    'housekeeping_status' => 'dirty'
                ]);
            }

            // Security: Explicitly drop Lodger PIN codes and destroy linked portal sessions upon check-out
            if ($reservation->guest_id) {
                \App\Models\Guest::where('id', $reservation->guest_id)->update(['pin_code' => null]);
                \App\Models\GuestPortalSession::where('reservation_id', $reservation->id)
                    ->orWhere('guest_id', $reservation->guest_id)
                    ->delete();
            }

            // Fire Event
            event(new GuestCheckedOut($reservation));

            return $reservation;
        });
    }
}
