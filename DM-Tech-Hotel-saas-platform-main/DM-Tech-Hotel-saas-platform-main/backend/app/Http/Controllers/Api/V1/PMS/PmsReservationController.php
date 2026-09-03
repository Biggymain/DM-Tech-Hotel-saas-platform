<?php

namespace App\Http\Controllers\Api\V1\PMS;
use App\Http\Controllers\Controller;


use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Services\ReservationService;
use App\Services\CheckInService;
use App\Services\CheckOutService;

class PmsReservationController extends Controller
{
    protected $reservationService;
    protected $checkInService;
    protected $checkOutService;

    public function __construct(
        ReservationService $reservationService,
        CheckInService $checkInService,
        CheckOutService $checkOutService
    ) {
        $this->reservationService = $reservationService;
        $this->checkInService = $checkInService;
        $this->checkOutService = $checkOutService;
    }

    /**
     * List all reservations for the hotel.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $hotelId = $request->header('x-hotel-context') ?? $user->hotel_id;
        $scope = $request->query('scope');

        $query = Reservation::with(['guest', 'rooms.roomType', 'folios', 'hotel']);

        if ($scope === 'group' && ($user->isGroupAdmin() || $user->is_super_admin)) {
            if (!$user->is_super_admin) {
                $query->whereHas('hotel', function($q) use ($user) {
                    $q->where('hotel_group_id', $user->hotel_group_id);
                });
            }
        } else {
            $query->where('hotel_id', $hotelId);
        }

        $reservations = $query->latest()->get();
            
        return response()->json(['success' => true, 'data' => $reservations]);
    }

    /**
     * Store a newly created reservation.
     */
    public function store(Request $request)
    {
        $request->validate([
            'guest_id' => 'nullable|exists:guests,id',
            'guest_first_name' => 'required_without:guest_id|string|max:255',
            'guest_last_name' => 'required_without:guest_id|string|max:255',
            'guest_email' => 'nullable|email|max:255',
            'guest_phone' => 'nullable|string|max:20',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
            'source' => 'nullable|in:walk_in,phone,website,booking_com,expedia,agent,api',
            'adults' => 'nullable|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'special_requests' => 'nullable|string',
            'rooms' => 'required|array|min:1',
            'rooms.*.id' => 'required|exists:rooms,id',
            'rooms.*.rate' => 'nullable|numeric|min:0',
        ]);

        $data = $request->all();
        $data['hotel_id'] = $request->user()->hotel_id 
            ?? $request->header('x-hotel-context') 
            ?? $request->hotel_id;

        if (empty($data['hotel_id'])) {
            return response()->json(['success' => false, 'message' => 'Hotel context missing.'], 400);
        }

        $reservation = $this->reservationService->createReservation($data);

        return response()->json([
            'success' => true,
            'data' => $reservation->load('rooms', 'guest'),
            'message' => 'Reservation created successfully.'
        ], 201);
    }

    /**
     * Update an existing reservation.
     */
    public function update(Request $request, Reservation $reservation)
    {
        $hotelId = $request->header('x-hotel-context') ?? $request->user()->hotel_id;
        
        if (!$request->user()->is_super_admin && $reservation->hotel_id != $hotelId) {
            abort(403, 'Unauthorized access.');
        }

        $data = $request->validate([
            'check_in_date' => 'nullable|date',
            'check_out_date' => 'nullable|date|after:check_in_date',
            'status' => 'nullable|string',
            'adults' => 'nullable|integer|min:1',
            'children' => 'nullable|integer|min:0',
            'special_requests' => 'nullable|string',
        ]);

        $updatedReservation = $this->reservationService->updateReservation($reservation, $data, $request->user());

        return response()->json([
            'success' => true,
            'data' => $updatedReservation->fresh(),
            'message' => 'Reservation updated successfully.'
        ]);
    }

    /**
     * Cancel an existing reservation.
     */
    public function destroy(Request $request, Reservation $reservation)
    {
        $hotelId = $request->header('x-hotel-context') ?? $request->user()->hotel_id;
        
        if (!$request->user()->is_super_admin && $reservation->hotel_id != $hotelId) {
            abort(403, 'Unauthorized access.');
        }

        $cancelledReservation = $this->reservationService->cancelReservation($reservation, $request->user());

        return response()->json([
            'success' => true,
            'data' => $cancelledReservation->fresh(),
            'message' => 'Reservation cancelled successfully.'
        ]);
    }

    /**
     * Confirm a pending reservation.
     */
    public function confirm(Request $request, Reservation $reservation)
    {
        $hotelId = $request->header('x-hotel-context') ?? $request->user()->hotel_id;
        
        if (!$request->user()->is_super_admin && $reservation->hotel_id != $hotelId) {
            abort(403, 'Unauthorized access.');
        }

        $this->reservationService->confirmReservation($reservation);
        
        return response()->json([
            'success' => true,
            'data' => $reservation->fresh(),
            'message' => 'Reservation confirmed.'
        ]);
    }

    /**
     * Check-in a guest.
     */
    public function checkIn(Request $request, Reservation $reservation)
    {
        $hotelId = $request->header('x-hotel-context') ?? $request->user()->hotel_id;
        
        if (!$request->user()->is_super_admin && $reservation->hotel_id != $hotelId) {
            abort(403, 'Unauthorized access.');
        }

        $this->checkInService->checkInGuest($reservation);

        return response()->json([
            'success' => true,
            'data' => $reservation->fresh(['folios', 'rooms']),
            'message' => 'Guest successfully checked in.'
        ]);
    }

    /**
     * Check-out a guest.
     */
    public function checkOut(Request $request, Reservation $reservation)
    {
        $hotelId = $request->header('x-hotel-context') ?? $request->user()->hotel_id;
        
        if (!$request->user()->is_super_admin && $reservation->hotel_id != $hotelId) {
            abort(403, 'Unauthorized access.');
        }

        $this->checkOutService->checkOutGuest($reservation);

        return response()->json([
            'success' => true,
            'data' => $reservation->fresh(['folios', 'rooms']),
            'message' => 'Guest successfully checked out.'
        ]);
    }

    /**
     * Extend a reservation stay.
     */
    public function extend(Request $request, Reservation $reservation)
    {
        $hotelId = $request->header('x-hotel-context') ?? $request->user()->hotel_id;
        
        if (!$request->user()->is_super_admin && $reservation->hotel_id != $hotelId) {
            abort(403, 'Unauthorized access.');
        }

        $request->validate([
            'days' => 'required|integer|min:1',
        ]);

        $extendedReservation = $this->reservationService->extendStay($reservation, $request->days, $request->user());

        return response()->json([
            'success' => true,
            'data' => $extendedReservation->fresh(['folios', 'rooms']),
            'message' => "Stay extended by {$request->days} day(s)."
        ]);
    }
}
