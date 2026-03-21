<?php

namespace App\Services\ChannelManager;

use App\Models\ChannelSyncLog;
use App\Models\Guest;
use App\Models\HotelChannelConnection;
use App\Models\OtaReservation;
use App\Models\Room;
use App\Services\ReservationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ChannelReservationService
{
    public function __construct(private ReservationService $reservationService) {}

    /**
     * Import an OTA reservation payload with overbooking lock protection.
     */
    public function importReservation(HotelChannelConnection $connection, array $payload): ?\App\Models\Reservation
    {
        $channel = $connection->otaChannel;
        $externalId = $payload['external_reservation_id'] ?? ($payload['channel_reservation_id'] ?? null);

        if (!$externalId) {
            throw ValidationException::withMessages(['external_reservation_id' => 'Missing OTA reservation ID.']);
        }

        // 1. Idempotency check — skip duplicates
        if (OtaReservation::where('ota_channel_id', $channel->id)->where('external_reservation_id', $externalId)->exists()) {
            Log::info("Duplicate OTA reservation skipped: {$externalId}");
            return null;
        }

        // 2. Identify room type via mapping
        $roomMap = \App\Models\RoomTypeChannelMap::where('hotel_id', $connection->hotel_id)
            ->where('ota_channel_id', $channel->id)
            ->where('external_room_type_id', $payload['room_identifier'] ?? '')
            ->first();

        if (!$roomMap) {
            $this->logSync($connection, 'reservation_pull', 'failed', $payload, null, 'Unmapped room type: ' . ($payload['room_identifier'] ?? 'N/A'));
            throw ValidationException::withMessages(['room_identifier' => 'No room type mapping found for this OTA room identifier.']);
        }

        // 3. DB transaction with row-level lock to prevent overbooking race conditions
        return DB::transaction(function () use ($connection, $payload, $roomMap, $channel, $externalId) {
            // Lock available rooms for this type to prevent double-booking
            $room = Room::where('hotel_id', $connection->hotel_id)
                ->where('room_type_id', $roomMap->room_type_id)
                ->where('status', 'available')
                ->lockForUpdate()
                ->first();

            if (!$room) {
                $this->logSync($connection, 'reservation_pull', 'failed', $payload, null, 'No available rooms for OTA booking.');
                throw ValidationException::withMessages(['room_type' => 'No available rooms for this OTA booking.']);
            }

            // 4. Find or create guest record
            $guestName = $payload['guest_name'] ?? ($payload['guest']['first_name'] . ' ' . $payload['guest']['last_name']);
            $guest = Guest::firstOrCreate(
                ['email' => $payload['guest']['email'] ?? null, 'hotel_id' => $connection->hotel_id],
                [
                    'first_name' => $payload['guest']['first_name'] ?? $guestName,
                    'last_name' => $payload['guest']['last_name'] ?? '',
                    'phone' => $payload['guest']['phone'] ?? null,
                ]
            );

            // 5. Create the PMS reservation
            $reservationData = [
                'hotel_id' => $connection->hotel_id,
                'guest_id' => $guest->id,
                'check_in_date' => $payload['check_in'],
                'check_out_date' => $payload['check_out'],
                'adults' => $payload['adults'] ?? 1,
                'children' => $payload['children'] ?? 0,
                'source' => 'ota',
                'special_requests' => $payload['special_requests'] ?? null,
                'rooms' => [['id' => $room->id]],
                'total_amount' => $payload['total_price'] ?? 0,
            ];

            $reservation = $this->reservationService->createReservation($reservationData);

            // 6. Store OTA reservation record for traceability
            OtaReservation::create([
                'hotel_id' => $connection->hotel_id,
                'ota_channel_id' => $channel->id,
                'external_reservation_id' => $externalId,
                'guest_name' => $guestName,
                'check_in' => $payload['check_in'],
                'check_out' => $payload['check_out'],
                'room_type' => $payload['room_identifier'] ?? '',
                'total_price' => $payload['total_price'] ?? 0,
                'status' => $payload['status'] ?? 'confirmed',
                'raw_payload' => $payload,
                'reservation_id' => $reservation->id,
            ]);

            // 7. Log success
            $this->logSync($connection, 'reservation_pull', 'success', $payload, ['pms_reservation_id' => $reservation->id], null);

            return $reservation;
        });
    }

    private function logSync(HotelChannelConnection $connection, string $operation, string $status, ?array $request, ?array $response, ?string $error): void
    {
        ChannelSyncLog::create([
            'hotel_id' => $connection->hotel_id,
            'ota_channel_id' => $connection->ota_channel_id,
            'operation' => $operation,
            'status' => $status,
            'request_payload' => $request,
            'response_payload' => $response,
            'error_message' => $error,
        ]);
    }
}
