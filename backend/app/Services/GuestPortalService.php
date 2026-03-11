<?php

namespace App\Services;

use App\Models\GuestPortalSession;
use App\Models\Room;
use App\Models\Reservation;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuestPortalService
{
    /**
     * Create a new portal session from a scanned QR code context.
     */
    public function createSessionFromContext(int $hotelId, string $contextType, $contextId, ?string $deviceInfo = null)
    {
        $reservation = null;
        $roomId = null;

        if ($contextType === 'room') {
            $roomId = $contextId;
            // Find active reservation for this room
            $reservation = Reservation::whereHas('rooms', function ($q) use ($roomId) {
                $q->where('rooms.id', $roomId);
            })
            ->whereIn('status', ['checked_in'])
            ->whereDate('check_in_date', '<=', now())
            ->whereDate('check_out_date', '>=', now())
            ->first();
        }

        // For security, only require a PIN if it's a room guest mode (and they have a reservation)
        $pinCode = ($contextType === 'room' && $reservation) ? strtolower($reservation->guest->last_name) : null;

        $session = GuestPortalSession::create([
            'hotel_id' => $hotelId,
            'room_id' => $roomId,
            'reservation_id' => $reservation ? $reservation->id : null,
            'guest_id' => $reservation ? $reservation->guest_id : null,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'session_token' => Str::random(64),
            'pin_code' => $pinCode,
            'device_info' => $deviceInfo,
            'expires_at' => now()->addHours(24),
            'is_active' => true,
        ]);

        return $session;
    }

    /**
     * Authenticate a session using PIN and optional fingerprint.
     */
    public function authenticateWithPin(string $sessionToken, ?string $pin, ?string $fingerprint = null)
    {
        $session = GuestPortalSession::where('session_token', $sessionToken)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->first();

        if (!$session) {
            throw ValidationException::withMessages(['session' => 'Invalid or expired session.']);
        }

        // If it's a trusted device with a matching fingerprint, bypass PIN
        if ($session->trusted_device && $session->device_fingerprint === $fingerprint && !empty($fingerprint)) {
            // Already authenticated
            return $session;
        }

        // Otherwise, validate PIN
        if ($session->pin_code !== $pin && !empty($session->pin_code)) {
            throw ValidationException::withMessages(['pin' => 'Invalid PIN.']);
        }
        
        // Save fingerprint if provided
        if ($fingerprint) {
            $session->update([
                'device_fingerprint' => $fingerprint,
                'trusted_device' => true,
            ]);
        }

        return $session;
    }

    /**
     * Get the dashboard data.
     */
    public function getGuestDashboard(GuestPortalSession $session)
    {
        $session->load(['reservation', 'room', 'guest']);
        
        $folioSummary = null;
        if ($session->reservation_id) {
            $folio = $session->reservation->folios()->first();
            if ($folio) {
                $folioSummary = [
                    'balance' => $folio->balance ?? 0,
                    'status' => $folio->status,
                ];
            }
        }

        return [
            'hotel_name' => $session->hotel_id ? \App\Models\Hotel::find($session->hotel_id)->name : 'Hotel',
            'room_number' => $session->room ? $session->room->room_number : null,
            'context_type' => $session->context_type,
            'context_id' => $session->context_id,
            'guest' => $session->guest,
            'reservation' => $session->reservation ? [
                'check_in_date' => $session->reservation->check_in_date,
                'check_out_date' => $session->reservation->check_out_date,
                'status' => $session->reservation->status,
            ] : null,
            'folio' => $folioSummary,
            'session' => [
                'token' => $session->session_token,
                'expires_at' => $session->expires_at
            ]
        ];
    }
}
