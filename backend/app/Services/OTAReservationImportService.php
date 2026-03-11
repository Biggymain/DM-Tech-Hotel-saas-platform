<?php

namespace App\Services;

use App\Models\ChannelIntegration;
use App\Models\ChannelReservation;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class OTAReservationImportService
{
    private ReservationService $reservationService;

    public function __construct(ReservationService $reservationService)
    {
        $this->reservationService = $reservationService;
    }

    /**
     * Processes an incoming OTA payload to create a PMS reservation
     */
    public function importReservation(ChannelIntegration $integration, array $payload)
    {
        // 1. Check for Duplicate Ingestion
        $channelReservationId = $payload['channel_reservation_id'] ?? null;
        
        if (!$channelReservationId) {
            throw ValidationException::withMessages(['channel_reservation_id' => 'Missing channel reservation ID']);
        }

        $exists = ChannelReservation::where('channel_integration_id', $integration->id)
            ->where('channel_reservation_id', $channelReservationId)
            ->exists();

        if ($exists) {
            // Log as duplicate skip, but don't fail the webhook
            Log::info("Duplicate channel reservation import skipped: {$channelReservationId}");
            return null; // Return cleanly to acknowledge webhook
        }

        // 2. Identify PMS Entities via Mappings
        $roomMapping = $integration->roomMappings()
            ->where('channel_room_identifier', $payload['room_identifier'])
            ->first();

        if (!$roomMapping) {
            throw ValidationException::withMessages(['room_identifier' => 'Unmapped room type from OTA']);
        }

        $ratePlanId = null;
        if (isset($payload['rate_identifier'])) {
            $rateMapping = $integration->rateMappings()
                ->where('channel_rate_identifier', $payload['rate_identifier'])
                ->first();
            if ($rateMapping) {
                $ratePlanId = $rateMapping->rate_plan_id;
            }
        }

        // 3. Assemble PMS Reservation Request Parameters
        $guestData = [
            'first_name' => $payload['guest']['first_name'],
            'last_name' => $payload['guest']['last_name'],
            'email' => $payload['guest']['email'] ?? null,
            'phone' => $payload['guest']['phone'] ?? null,
        ];

        // Ensure check-in/out times match hotel standards.
        $hotel = $integration->hotel;
        $checkInTime = $hotel->check_in_time ? " {$hotel->check_in_time}" : " 15:00:00";
        $checkOutTime = $hotel->check_out_time ? " {$hotel->check_out_time}" : " 11:00:00";

        // Assume the OTA payload total amount overrides the PMS dynamic pricing since it was sold remotely.
        // We simulate passing an arbitrary total to createReservation, although in robust systems
        // we might just lock it immediately.
        
        // Let's create the guest internally or find existing (ReservationService does this if we pass details)
        
        // Assuming ReservationService takes raw arrays per existing architecture context
        // OR we can create the Guest explicitly if ReservationService demands guest_id
        $guest = \App\Models\Guest::firstOrCreate(
            ['email' => $guestData['email'], 'hotel_id' => $integration->hotel_id],
            ['first_name' => $guestData['first_name'], 'last_name' => $guestData['last_name'], 'phone' => $guestData['phone']]
        );

        // Find an available physical room of this type
        $availableRooms = $this->reservationService->getAvailableRooms(
            $integration->hotel_id, 
            $payload['check_in_date'], 
            $payload['check_out_date'], 
            $roomMapping->room_type_id
        );

        if ($availableRooms->isEmpty()) {
            throw ValidationException::withMessages(['room_type' => 'No available physical rooms for this OTA booking.']);
        }
        $assignedRoom = $availableRooms->first();

        $reservationData = [
            'hotel_id' => $integration->hotel_id,
            'guest_id' => $guest->id,
            'check_in_date' => $payload['check_in_date'] . $checkInTime,
            'check_out_date' => $payload['check_out_date'] . $checkOutTime,
            'adults' => $payload['adults'] ?? 1,
            'children' => $payload['children'] ?? 0,
            'source' => 'ota', // Mapped enum
            'special_requests' => $payload['special_requests'] ?? null,
            'rate_plan_id' => $ratePlanId,
            'rooms' => [
                ['id' => $assignedRoom->id]
            ]
        ];

        // 4. Create Reservation
        $reservation = $this->reservationService->createReservation($reservationData);

        // 5. If OTA passes an overriding paid amount, we align it
        // Depending on existing architecture rules we might generate an invoice/payment
        
        // 6. Link it inside channel_reservations
        ChannelReservation::create([
            'hotel_id' => $integration->hotel_id,
            'channel_integration_id' => $integration->id,
            'reservation_id' => $reservation->id,
            'channel_reservation_id' => $channelReservationId,
            'raw_payload' => $payload,
            'imported_at' => now()
        ]);
        
        // Log Success
        \App\Models\ChannelSyncLog::create([
            'hotel_id' => $integration->hotel_id,
            'channel_integration_id' => $integration->id,
            'sync_type' => 'reservation_import',
            'status' => 'success',
            'request_payload' => $payload,
            'response_payload' => ['pms_reservation_id' => $reservation->id],
            'synced_at' => now()
        ]);

        return $reservation;
    }
}
