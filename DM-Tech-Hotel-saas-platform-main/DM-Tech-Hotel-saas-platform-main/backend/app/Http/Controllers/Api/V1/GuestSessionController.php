<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Reservation;
use Illuminate\Validation\ValidationException;

class GuestSessionController extends Controller
{
    /**
     * Claim a room by setting a session PIN on the reservation.
     * Used on Port 3004 (Guest Menu).
     */
    public function claimRoom(Request $request)
    {
        $request->validate([
            'reservation_id' => 'required',
            'pin'            => 'required|string|size:4',
        ]);

        $reservation = Reservation::find($request->reservation_id);

        if (!$reservation) {
            throw ValidationException::withMessages([
                'reservation_id' => ['Invalid reservation ID.'],
            ]);
        }

        // Update the session_pin on the reservation
        $reservation->update([
            'session_pin' => $request->pin,
        ]);

        return response()->json([
            'message' => 'Room claimed successfully. Session PIN set.',
            'reservation' => [
                'id' => $reservation->id,
                'reservation_number' => $reservation->reservation_number,
                'status' => $reservation->status,
            ],
        ]);
    }

    /**
     * Verify a Guest Session PIN.
     */
    public function verifyPin(Request $request)
    {
        $request->validate([
            'reservation_id' => 'required',
            'pin'            => 'required|string|size:4',
        ]);

        $reservation = Reservation::where('id', $request->reservation_id)
            ->where('session_pin', $request->pin)
            ->first();

        if (!$reservation) {
            return response()->json(['message' => 'Invalid session PIN.'], 403);
        }

        return response()->json([
            'message' => 'PIN verified correctly.',
            'valid' => true,
        ]);
    }
}
