<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\Guest;
use App\Models\User;
use App\Events\ReservationCreated;
use App\Events\ReservationConfirmed;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    /**
     * Create a new reservation and attach rooms with rate overrides.
     */
    public function createReservation(array $data)
    {
        $this->validateDates($data['check_in_date'], $data['check_out_date']);

        $hotel = Hotel::find($data['hotel_id']);
        $modificationDeadline = null;
        if ($hotel && $hotel->reservation_deadline_hours_before_checkin !== null) {
            // Assume 14:00 (2:00 PM) as default check-in time if no specific time is provided.
            $checkInDateTime = Carbon::parse($data['check_in_date'])->startOfDay()->addHours(14);
            $modificationDeadline = $checkInDateTime->subHours($hotel->reservation_deadline_hours_before_checkin);
        }

        return DB::transaction(function () use ($data, $modificationDeadline) {
            $now = now();
            $checkInDate = Carbon::parse($data['check_in_date']);

            // Create guest if guest_id is missing
            if (empty($data['guest_id'])) {
                $guest = Guest::create([
                    'hotel_id' => $data['hotel_id'],
                    'first_name' => $data['guest_first_name'],
                    'last_name' => $data['guest_last_name'],
                    'email' => $data['guest_email'] ?? null,
                    'phone' => $data['guest_phone'] ?? null,
                ]);
                $data['guest_id'] = $guest->id;
            }

            // Nigeria Rule: If arrival is before 8:00 AM today, it effectively belongs to yesterday's stay
            // We only apply this if the staff is checking in for "Today"
            if ($checkInDate->isToday() && $now->hour < 8) {
                $checkInDate = $checkInDate->subDay();
            }

            $checkOutDate = Carbon::parse($data['check_out_date']);

            // If number_of_days is provided, override check_out_date
            if (isset($data['number_of_days']) && $data['number_of_days'] > 0) {
                $checkOutDate = $checkInDate->copy()->addDays($data['number_of_days']);
            }

            $reservation = Reservation::create([
                'hotel_id' => $data['hotel_id'],
                'guest_id' => $data['guest_id'],
                'reservation_number' => $this->generateReservationNumber($data['hotel_id']),
                'check_in_date' => $checkInDate->toDateString(),
                'check_out_date' => $checkOutDate->toDateString(),
                'status' => 'pending',
                'source' => $data['source'] ?? 'walk_in',
                'total_amount' => 0, // Calculated after attaching rooms
                'adults' => $data['adults'] ?? 1,
                'children' => $data['children'] ?? 0,
                'special_requests' => $data['special_requests'] ?? null,
                'modification_deadline' => $modificationDeadline,
                'deposit_amount' => $data['deposit_amount'] ?? null,
                'deposit_paid' => $data['deposit_paid'] ?? false,
                'rate_plan_id' => $data['rate_plan_id'] ?? null,
            ]);

            $totalAmount = 0;
            $nights = $checkInDate->diffInDays($checkOutDate);
            if ($nights <= 0) $nights = 1; // Minimum 1 night if same day/invalid
            
            $pricingService = app(\App\Services\PricingService::class);
            $ratePlan = null;
            if (isset($data['rate_plan_id'])) {
                $ratePlan = \App\Models\RatePlan::find($data['rate_plan_id']);
            }

            foreach ($data['rooms'] as $roomData) {
                $room = Room::with('roomType')->findOrFail($roomData['id']);
                
                // Assert Availability
                if (!$this->isRoomAvailable($room, $data['check_in_date'], $data['check_out_date'])) {
                    throw ValidationException::withMessages([
                        'room' => "Room {$room->room_number} is not available for the selected dates."
                    ]);
                }

                if (isset($roomData['rate'])) {
                    $rate = $roomData['rate'];
                } else {
                    $calcDate = Carbon::parse($data['check_in_date']);
                    $rate = $pricingService->calculateRoomPrice($room->roomType, $calcDate, $ratePlan);
                }
                
                $reservation->rooms()->attach($room->id, ['rate' => $rate]);
                $totalAmount += ($rate * $nights);
            }

            $reservation->update([
                'total_amount' => $totalAmount,
                'locked_price' => $totalAmount
            ]);

            event(new ReservationCreated($reservation));

            return $reservation;
        });
    }

    /**
     * Confirm a reservation.
     */
    public function confirmReservation(Reservation $reservation)
    {
        $reservation->update(['status' => 'confirmed']);
        event(new ReservationConfirmed($reservation));
        
        return $reservation;
    }

    /**
     * Check if a specific room is available between two dates.
     */
    public function isRoomAvailable(Room $room, string $checkInDate, string $checkOutDate): bool
    {
        // Check maintenance constraints explicitly
        if ($room->status === 'maintenance' || $room->status === 'out_of_order') {
             if ($room->maintenance_until && Carbon::parse($room->maintenance_until)->isAfter(now())) {
                 return false;
             }
        }

        // Check overlapping bookings
        $hasOverlap = $room->reservations()
            ->whereIn('status', ['pending', 'confirmed', 'checked_in'])
            ->where(function ($query) use ($checkInDate, $checkOutDate) {
                // An overlap occurs if the existing booking's checkin is strictly before the new checkout,
                // AND the existing booking's checkout is strictly after the new checkin.
                $query->where('check_in_date', '<', $checkOutDate)
                      ->where('check_out_date', '>', $checkInDate);
            })->exists();

        return !$hasOverlap;
    }

    /**
     * Get all available rooms for a hotel within a date range optionally filtered by room type.
     */
    public function getAvailableRooms(int $hotelId, string $checkInDate, string $checkOutDate, ?int $roomTypeId = null)
    {
        $this->validateDates($checkInDate, $checkOutDate);

        $query = Room::where('hotel_id', $hotelId)
            ->whereNotIn('status', ['maintenance', 'out_of_order']);

        if ($roomTypeId) {
            $query->where('room_type_id', $roomTypeId);
        }

        // Exclude rooms that have overlapping reservations
        $query->whereDoesntHave('reservations', function ($q) use ($checkInDate, $checkOutDate) {
            $q->whereIn('status', ['pending', 'confirmed', 'checked_in'])
              ->where('check_in_date', '<', $checkOutDate)
              ->where('check_out_date', '>', $checkInDate);
        });

        return $query->get();
    }

    /**
     * Generate a unique Reservation Number per hotel.
     */
    private function generateReservationNumber(int $hotelId): string
    {
        do {
            $number = 'RSV-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));
            $exists = Reservation::where('hotel_id', $hotelId)->where('reservation_number', $number)->exists();
        } while ($exists);

        return $number;
    }

    /**
     * Update a reservation.
     */
    public function updateReservation(Reservation $reservation, array $data, ?User $user = null)
    {
        $this->enforceModificationDeadline($reservation, $user, 'modification');

        if (isset($data['check_in_date']) && isset($data['check_out_date'])) {
            $this->validateDates($data['check_in_date'], $data['check_out_date']);
        }

        $reservation->update($data);
        return $reservation;
    }

    /**
     * Extend an existing reservation by a number of days.
     */
    public function extendStay(Reservation $reservation, int $days, ?User $user = null)
    {
        $this->enforceModificationDeadline($reservation, $user, 'extension');

        return DB::transaction(function () use ($reservation, $days) {
            $oldCheckOut = Carbon::parse($reservation->check_out_date);
            $newCheckOut = $oldCheckOut->copy()->addDays($days);
            
            // Check if current rooms are available for the extension period
            $conflictingRooms = [];
            foreach ($reservation->rooms as $room) {
                if (!$this->isRoomAvailable($room, $oldCheckOut->toDateString(), $newCheckOut->toDateString())) {
                    $conflictingRooms[] = $room;
                }
            }

            if (empty($conflictingRooms)) {
                // Scenario A: No conflicts, just extend the current reservation
                $reservation->check_out_date = $newCheckOut->toDateString();
                $reservation->status = 'extended'; // Mark as extended
                
                // Recalculate total amount
                $nights = Carbon::parse($reservation->check_in_date)->diffInDays($newCheckOut);
                $totalAmount = 0;
                foreach ($reservation->rooms as $room) {
                    $totalAmount += ($room->pivot->rate * $nights);
                }
                $reservation->total_amount = $totalAmount;
                $reservation->save();
                
                return $reservation;
            } else {
                // Scenario B: Conflict exists, MUST switch rooms for the extension period
                // We create a LINKED reservation for the extra days in a different room
                $extensionReservation = Reservation::create([
                    'hotel_id'          => $reservation->hotel_id,
                    'guest_id'          => $reservation->guest_id,
                    'parent_id'         => $reservation->id,
                    'check_in_date'     => $oldCheckOut->toDateString(),
                    'check_out_date'    => $newCheckOut->toDateString(),
                    'status'            => 'extended',
                    'source'            => $reservation->source,
                    'adults'            => $reservation->adults,
                    'children'          => $reservation->children,
                    'total_amount'      => 0, // Calculated below
                ]);

                $totalExtensionAmount = 0;
                foreach ($reservation->rooms as $room) {
                    $rate = $room->pivot->rate;
                    
                    if (in_array($room->id, array_map(fn($r) => $r->id, $conflictingRooms))) {
                        // Find a NEW room of the same type
                        $newRoom = $this->getAvailableRooms(
                            $reservation->hotel_id, 
                            $oldCheckOut->toDateString(), 
                            $newCheckOut->toDateString(), 
                            $room->room_type_id
                        )->first();

                        if (!$newRoom) {
                            throw ValidationException::withMessages([
                                'extension' => "Cannot extend: No alternative rooms of type {$room->roomType->name} available for the extension period."
                            ]);
                        }
                        $extensionReservation->rooms()->attach($newRoom->id, ['rate' => $rate]);
                    } else {
                        // Room is free, can stay in original room for extension
                        $extensionReservation->rooms()->attach($room->id, ['rate' => $rate]);
                    }
                    $totalExtensionAmount += ($rate * $days);
                }

                $extensionReservation->update(['total_amount' => $totalExtensionAmount]);
                
                return $extensionReservation;
            }
        });
    }

    /**
     * Cancel a reservation.
     */
    public function cancelReservation(Reservation $reservation, ?User $user = null)
    {
        $this->enforceModificationDeadline($reservation, $user, 'cancellation');

        $reservation->update(['status' => 'cancelled']);
        event(new \App\Events\ReservationCancelled($reservation));
        
        return $reservation;
    }

    /**
     * Enforces the modification deadline logic.
     */
    private function enforceModificationDeadline(Reservation $reservation, ?User $user, string $actionType)
    {
        if ($reservation->modification_deadline && now()->isAfter($reservation->modification_deadline)) {
            $isAdmin = false;
            if ($user && ($user->is_super_admin || $user->roles->contains(function ($role) {
                return in_array($role->name, ['Manager', 'Admin', 'HotelOwner', 'SuperAdmin']);
            }))) {
                $isAdmin = true;
            }

            if (!$isAdmin) {
                throw ValidationException::withMessages([
                    'modification_deadline' => "The {$actionType} deadline for this reservation has passed and cannot be performed."
                ]);
            }
        }
    }

    /**
     * Validate CheckIn and CheckOut chronology.
     */
    private function validateDates(string $checkInDate, string $checkOutDate)
    {
        $in = Carbon::parse($checkInDate)->startOfDay();
        $out = Carbon::parse($checkOutDate)->startOfDay();

        if ($out->lte($in)) {
            throw ValidationException::withMessages([
                'check_out_date' => 'Check-out date must be strictly after the check-in date.'
            ]);
        }
    }
}
