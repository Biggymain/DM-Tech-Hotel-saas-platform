<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Folio;
use App\Models\Room;
use App\Events\GuestCheckedIn;
use App\Events\FolioOpened;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInService
{
    /**
     * Complete the CheckIn workflow for a confirmed reservation.
     */
    public function checkInGuest(Reservation $reservation)
    {
        if ($reservation->status !== 'confirmed') {
            throw ValidationException::withMessages([
                'status' => 'Only confirmed reservations can be checked in.'
            ]);
        }

        return DB::transaction(function () use ($reservation) {
            // Update Reservation Status
            $reservation->update(['status' => 'checked_in']);

            // Update attached rooms status
            foreach ($reservation->rooms as $room) {
                // Ensure room isn't out_of_order or maintenance
                if (in_array($room->status, ['maintenance', 'out_of_order'])) {
                     throw ValidationException::withMessages([
                         'room' => "Room {$room->room_number} is out of service. Please reallocate."
                     ]);
                }

                $room->update(['status' => 'occupied']);
            }

            // Open a Folio
            $folio = Folio::create([
                'hotel_id' => $reservation->hotel_id,
                'reservation_id' => $reservation->id,
                'status' => 'open',
                'currency' => $reservation->hotel->currency_id ?? 'USD', // Fallback to Hotel default
                'total_charges' => 0,
                'total_payments' => 0,
                'balance' => 0,
            ]);

            // Fire Events
            event(new GuestCheckedIn($reservation));
            event(new FolioOpened($folio));

            return $reservation;
        });
    }
}
