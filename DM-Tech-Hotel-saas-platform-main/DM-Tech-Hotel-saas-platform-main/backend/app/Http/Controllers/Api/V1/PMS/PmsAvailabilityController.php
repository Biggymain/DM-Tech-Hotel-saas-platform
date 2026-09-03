<?php

namespace App\Http\Controllers\Api\V1\PMS;
use App\Http\Controllers\Controller;


use Illuminate\Http\Request;
use App\Services\ReservationService;
use Illuminate\Validation\ValidationException;

class PmsAvailabilityController extends Controller
{
    protected $reservationService;

    public function __construct(ReservationService $reservationService)
    {
        $this->reservationService = $reservationService;
    }

    /**
     * Check available rooms for a given date range.
     */
    public function index(Request $request)
    {
        $request->validate([
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
            'room_type_id' => 'nullable|exists:room_types,id',
        ]);

        $hotelId = $request->user()->hotel_id;

        try {
            $availableRooms = $this->reservationService->getAvailableRooms(
                $hotelId,
                $request->check_in_date,
                $request->check_out_date,
                $request->room_type_id
            );

            return response()->json([
                'success' => true,
                'data' => $availableRooms,
                'message' => 'Available rooms retrieved successfully.'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Date Range',
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
