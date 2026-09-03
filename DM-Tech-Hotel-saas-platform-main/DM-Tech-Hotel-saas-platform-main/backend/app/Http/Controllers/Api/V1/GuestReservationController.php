<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use Illuminate\Http\Request;
use App\Models\GuestPortalSession;
use App\Models\Guest;
use App\Services\ReservationService;

class GuestReservationController extends Controller
{
    protected $reservationService;

    public function __construct(ReservationService $reservationService)
    {
        $this->reservationService = $reservationService;
    }

    public function searchAvailability(Request $request)
    {
        $session = $this->getActiveSession($request);

        $request->validate([
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'adults' => 'required|integer|min:1',
        ]);

        $availableRoomTypes = $this->reservationService->getAvailableRooms(
            $session->hotel_id,
            $request->check_in_date,
            $request->check_out_date
        );

        return response()->json([
            'available_room_types' => $availableRoomTypes
        ]);
    }

    public function store(Request $request)
    {
        $session = $this->getActiveSession($request);

        $validated = $request->validate([
            'guest.first_name' => 'required|string',
            'guest.last_name' => 'required|string',
            'guest.email' => 'required|email',
            'guest.phone' => 'nullable|string',
            'room_id' => 'required|exists:rooms,id',
            'rate' => 'nullable|numeric|min:0',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'adults' => 'required|integer|min:1',
            'children' => 'nullable|integer|min:0',
        ]);

        $guest = Guest::firstOrCreate(
            ['email_bidx' => hash_hmac('sha256', strtolower(trim($validated['guest']['email'])), config('app.key')), 'hotel_id' => $session->hotel_id],
            [
                'first_name' => $validated['guest']['first_name'],
                'last_name' => $validated['guest']['last_name'],
                'email' => $validated['guest']['email'],
                'phone' => $validated['guest']['phone'] ?? null,
            ]
        );

        $reservationData = [
            'hotel_id' => $session->hotel_id,
            'guest_id' => $guest->id,
            'check_in_date' => $validated['check_in_date'],
            'check_out_date' => $validated['check_out_date'],
            'adults' => $validated['adults'],
            'children' => $validated['children'] ?? 0,
            'source' => 'website',
            'special_requests' => 'Booked via Guest Portal',
            'rooms' => [
                [
                    'id' => $validated['room_id'],
                    'rate' => $validated['rate'] ?? 0,
                ]
            ]
        ];

        $reservation = $this->reservationService->createReservation($reservationData);

        return response()->json([
            'message' => 'Reservation created successfully. Please proceed to payment.',
            'reservation' => $reservation->load('rooms', 'guest')
        ], 201);
    }

    private function getActiveSession(Request $request)
    {
        $token = $request->header('X-Guest-Session') ?? $request->session_token ?? $request->bearerToken();
        
        $session = GuestPortalSession::where('session_token', $token)
            ->where('status', '!=', 'revoked')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        return $session;
    }
}
