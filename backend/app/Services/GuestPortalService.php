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
     * Create a new portal session from a scanned room QR code.
     */
    public function createSessionFromQR(Room $room, ?string $deviceInfo = null)
    {
        // Find active reservation for this room
        $reservation = Reservation::whereHas('rooms', function ($q) use ($room) {
            $q->where('rooms.id', $room->id);
        })
        ->whereIn('status', ['checked_in'])
        ->whereDate('check_in_date', '<=', now())
        ->whereDate('check_out_date', '>=', now())
        ->first();

        // For security, require a reservation to generate a pin setup.
        // The PIN defaults to the guest's last name or a custom value in a real scenario.
        $pinCode = $reservation ? strtolower($reservation->guest->last_name) : '1234';

        $session = GuestPortalSession::create([
            'hotel_id' => $room->hotel_id,
            'room_id' => $room->id,
            'reservation_id' => $reservation ? $reservation->id : null,
            'guest_id' => $reservation ? $reservation->guest_id : null,
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
            'hotel_name' => $session->room->hotel->name ?? 'Hotel',
            'room_number' => $session->room->room_number,
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
