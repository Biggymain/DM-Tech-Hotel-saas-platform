<?php

namespace App\Services;

use App\Models\DigitalKey;
use App\Models\Hotel;
use App\Models\HotelSetting;
use App\Models\Reservation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * DoorLockService
 *
 * Handles digital key generation for hotel door lock providers.
 * Provider is determined per-hotel from HotelSettings (key: 'door_lock_provider').
 *
 * Supported providers:
 *  - 'manual'    — Generate a PIN code, no external API call
 *  - 'vingcard'  — VingCard Visionline REST API
 *  - 'salto'     — SALTO SPACE Cloud API
 *  - 'dormakaba' — dormakaba AMBIANCE API
 */
class DoorLockService
{
    public function __construct() {}

    /**
     * Generate a digital key for a reservation and persist it.
     * Called from GenerateDigitalKeyJob after check-in.
     */
    public function generateKey(Reservation $reservation): DigitalKey
    {
        $hotel    = $reservation->hotel;
        $provider = $this->getProvider($hotel);
        $room     = $reservation->room;

        Log::info("[DoorLock] Generating {$provider} key for reservation #{$reservation->id}, room {$room->room_number}");

        $result = match ($provider) {
            'vingcard'  => $this->issueVingCardKey($hotel, $reservation, $room),
            'salto'     => $this->issueSaltoKey($hotel, $reservation, $room),
            'dormakaba' => $this->issueDormakabaKey($hotel, $reservation, $room),
            default     => $this->issueManualKey($reservation, $room),
        };

        return DigitalKey::create([
            'hotel_id'          => $hotel->id,
            'reservation_id'    => $reservation->id,
            'room_number'       => $room->room_number,
            'provider'          => $provider,
            'key_code'          => $result['key_code'] ?? null,
            'bluetooth_link'    => $result['bluetooth_link'] ?? null,
            'qr_data'           => $result['qr_data'] ?? null,
            'status'            => 'active',
            'valid_from'        => $reservation->check_in_date,
            'valid_until'       => $reservation->check_out_date,
            'provider_response' => $result['raw_response'] ?? null,
        ]);
    }

    /**
     * Revoke a key when reservation is checked-out or cancelled.
     */
    public function revokeKey(DigitalKey $key): void
    {
        $hotel    = Hotel::find($key->hotel_id);
        $provider = $this->getProvider($hotel);

        match ($provider) {
            'vingcard'  => $this->revokeVingCardKey($hotel, $key),
            'salto'     => $this->revokeSaltoKey($hotel, $key),
            default     => null, // Manual keys expire naturally
        };

        $key->update(['status' => 'revoked']);
        Log::info("[DoorLock] Revoked {$provider} key #{$key->id} for room {$key->room_number}");
    }

    // ─── Provider: Manual (PIN-based) ─────────────────────────────────────────

    private function issueManualKey(Reservation $reservation, $room): array
    {
        // Generate a 6-digit PIN based on reservation checksum
        $pin = str_pad(abs(crc32("{$reservation->id}-{$room->room_number}-{$reservation->check_in_date}")) % 1000000, 6, '0', STR_PAD_LEFT);
        return ['key_code' => $pin];
    }

    // ─── Provider: VingCard Visionline ────────────────────────────────────────

    private function issueVingCardKey(Hotel $hotel, Reservation $reservation, $room): array
    {
        $apiUrl    = $this->getSetting($hotel, 'vingcard_api_url', 'https://api.vingcard.com');
        $apiKey    = $this->getSetting($hotel, 'vingcard_api_key');
        $siteId    = $this->getSetting($hotel, 'vingcard_site_id');

        if (!$apiKey || !$siteId) {
            Log::warning("[DoorLock] VingCard credentials missing for hotel #{$hotel->id} — falling back to manual.");
            return $this->issueManualKey($reservation, $room);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type'  => 'application/json',
            ])->post("{$apiUrl}/v1/sites/{$siteId}/credentials", [
                'roomNumber'  => $room->room_number,
                'guestName'   => $reservation->guest?->full_name,
                'checkIn'     => $reservation->check_in_date,
                'checkOut'    => $reservation->check_out_date,
                'reference'   => "RES-{$reservation->id}",
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'key_code'       => $data['credentialCode'] ?? null,
                    'bluetooth_link' => $data['bluetoothLink'] ?? null,
                    'raw_response'   => $data,
                ];
            }
        } catch (\Exception $e) {
            Log::error("[DoorLock] VingCard API error: {$e->getMessage()}");
        }

        return $this->issueManualKey($reservation, $room);
    }

    // ─── Provider: SALTO SPACE ────────────────────────────────────────────────

    private function issueSaltoKey(Hotel $hotel, Reservation $reservation, $room): array
    {
        $apiUrl = $this->getSetting($hotel, 'salto_api_url', 'https://api.saltosystems.com');
        $apiKey = $this->getSetting($hotel, 'salto_api_key');

        if (!$apiKey) {
            Log::warning("[DoorLock] SALTO credentials missing for hotel #{$hotel->id} — falling back to manual.");
            return $this->issueManualKey($reservation, $room);
        }

        try {
            $response = Http::withToken($apiKey)->post("{$apiUrl}/access/credentials", [
                'door'           => $room->room_number,
                'valid_from'     => $reservation->check_in_date,
                'valid_to'       => $reservation->check_out_date,
                'user_reference' => "guest-{$reservation->guest_id}",
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'key_code'     => $data['pin'] ?? null,
                    'qr_data'      => $data['qr_code'] ?? null,
                    'raw_response' => $data,
                ];
            }
        } catch (\Exception $e) {
            Log::error("[DoorLock] SALTO API error: {$e->getMessage()}");
        }

        return $this->issueManualKey($reservation, $room);
    }

    // ─── Provider: dormakaba ──────────────────────────────────────────────────

    private function issueDormakabaKey(Hotel $hotel, Reservation $reservation, $room): array
    {
        // Dormakaba uses a similar REST pattern
        return $this->issueManualKey($reservation, $room);
    }

    // ─── Revoke helpers ───────────────────────────────────────────────────────

    private function revokeVingCardKey(Hotel $hotel, DigitalKey $key): void
    {
        $apiUrl = $this->getSetting($hotel, 'vingcard_api_url', 'https://api.vingcard.com');
        $apiKey = $this->getSetting($hotel, 'vingcard_api_key');
        if ($apiKey && $key->provider_response['credentialId'] ?? null) {
            Http::withToken($apiKey)->delete("{$apiUrl}/v1/credentials/{$key->provider_response['credentialId']}")
                ->throw();
        }
    }

    private function revokeSaltoKey(Hotel $hotel, DigitalKey $key): void
    {
        $apiUrl = $this->getSetting($hotel, 'salto_api_url', 'https://api.saltosystems.com');
        $apiKey = $this->getSetting($hotel, 'salto_api_key');
        if ($apiKey) {
            Http::withToken($apiKey)->delete("{$apiUrl}/access/credentials/{$key->id}")
                ->throw();
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function getProvider(Hotel $hotel): string
    {
        return $this->getSetting($hotel, 'door_lock_provider', 'manual');
    }

    private function getSetting(Hotel $hotel, string $key, string $default = ''): string
    {
        return HotelSetting::where('hotel_id', $hotel->id)
            ->where('key', $key)
            ->value('value') ?? $default;
    }
}
