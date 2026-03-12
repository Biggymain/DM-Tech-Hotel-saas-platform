<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\Outlet;
use App\Models\Reservation;
use App\Models\Folio;
use App\Models\FolioItem;
use App\Models\Guest;
use App\Services\PaymentGatewayResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * PublicBookingController
 *
 * Handles the guest-facing public booking engine.
 * Routes are protected by DomainTenantMiddleware — no auth required.
 * All queries are automatically scoped to the resolved hotel via TenantScope.
 */
class PublicBookingController extends Controller
{
    public function __construct(private PaymentGatewayResolver $paymentResolver) {}

    /**
     * GET /api/v1/booking/{hotel_slug}
     * Returns branch info, public room types, outlets, and theme config.
     */
    public function show(string $hotel_slug)
    {
        $hotel = Hotel::withoutGlobalScopes()
            ->with(['group', 'settings'])
            ->where('subdomain_slug', $hotel_slug)
            ->orWhere('domain', $hotel_slug)
            ->firstOrFail();

        // Public room types only
        $roomTypes = RoomType::withoutGlobalScopes()
            ->where('hotel_id', $hotel->id)
            ->where('is_public', true)
            ->select(['id', 'name', 'description', 'base_price', 'capacity'])
            ->get();

        // Public outlets (for QR menu / dine-in reservations)
        $outlets = Outlet::withoutGlobalScopes()
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->select(['id', 'name', 'type'])
            ->get();

        // Theme from group (or defaults)
        $theme = [
            'primary_color' => $hotel->group?->primary_color ?? '#6366f1',
            'accent_color'  => $hotel->group?->accent_color  ?? '#8b5cf6',
            'logo_url'      => $hotel->group?->logo_url      ?? null,
            'hotel_name'    => $hotel->name,
            'currency'      => $hotel->group?->currency       ?? 'USD',
        ];

        // Payment gateway public key (safe — no secret)
        $gateway = $this->paymentResolver->publicConfig($hotel);

        return response()->json([
            'hotel'      => [
                'id'      => $hotel->id,
                'name'    => $hotel->name,
                'email'   => $hotel->email,
                'address' => $hotel->address,
            ],
            'theme'      => $theme,
            'room_types' => $roomTypes,
            'outlets'    => $outlets,
            'payment'    => $gateway,
        ]);
    }

    /**
     * GET /api/v1/booking/{hotel_slug}/availability
     * Check available rooms for a date range.
     */
    public function availability(Request $request, string $hotel_slug)
    {
        $request->validate([
            'check_in'  => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'guests'    => 'nullable|integer|min:1',
        ]);

        $hotel = Hotel::withoutGlobalScopes()
            ->where('subdomain_slug', $hotel_slug)
            ->firstOrFail();

        $checkIn  = Carbon::parse($request->check_in);
        $checkOut = Carbon::parse($request->check_out);
        $nights   = $checkIn->diffInDays($checkOut);

        // Get room types with available room count
        $roomTypes = RoomType::withoutGlobalScopes()
            ->where('hotel_id', $hotel->id)
            ->where('is_public', true)
            ->with(['rooms' => function ($q) use ($checkIn, $checkOut) {
                // Rooms that do NOT have an overlapping reservation
                $q->whereDoesntHave('reservations', function ($r) use ($checkIn, $checkOut) {
                    $r->whereNotIn('status', ['cancelled', 'no_show'])
                      ->where('check_in_date', '<', $checkOut)
                      ->where('check_out_date', '>', $checkIn);
                })->where('status', 'available');
            }])
            ->get()
            ->map(fn($rt) => [
                'id'             => $rt->id,
                'name'           => $rt->name,
                'description'    => $rt->description,
                'base_price'     => $rt->base_price,
                'total_price'    => round($rt->base_price * $nights, 2),
                'nights'         => $nights,
                'capacity'       => $rt->capacity,
                'available_rooms' => $rt->rooms->count(),
            ])
            ->filter(fn($rt) => $rt['available_rooms'] > 0)
            ->values();

        return response()->json([
            'check_in'   => $checkIn->toDateString(),
            'check_out'  => $checkOut->toDateString(),
            'nights'     => $nights,
            'room_types' => $roomTypes,
        ]);
    }

    /**
     * POST /api/v1/booking/{hotel_slug}/reserve
     * Creates a reservation and initiates payment.
     * On successful payment callback, status changes to "confirmed".
     */
    public function reserve(Request $request, string $hotel_slug)
    {
        $validated = $request->validate([
            'room_type_id' => 'required|integer',
            'check_in'     => 'required|date|after_or_equal:today',
            'check_out'    => 'required|date|after:check_in',
            'guest_name'   => 'required|string|max:255',
            'guest_email'  => 'required|email',
            'guest_phone'  => 'nullable|string',
            'adults'       => 'nullable|integer|min:1',
            'children'     => 'nullable|integer|min:0',
            'special_requests' => 'nullable|string|max:1000',
        ]);

        $hotel = Hotel::withoutGlobalScopes()
            ->where('subdomain_slug', $hotel_slug)
            ->firstOrFail();

        $roomType = RoomType::withoutGlobalScopes()
            ->where('hotel_id', $hotel->id)
            ->where('id', $validated['room_type_id'])
            ->where('is_public', true)
            ->firstOrFail();

        // Find an available room of this type
        $checkIn  = Carbon::parse($validated['check_in']);
        $checkOut = Carbon::parse($validated['check_out']);
        $nights   = $checkIn->diffInDays($checkOut);

        $availableRoom = $roomType->rooms()
            ->whereDoesntHave('reservations', function ($q) use ($checkIn, $checkOut) {
                $q->whereNotIn('status', ['cancelled', 'no_show'])
                  ->where('check_in_date', '<', $checkOut)
                  ->where('check_out_date', '>', $checkIn);
            })
            ->where('status', 'available')
            ->first();

        if (!$availableRoom) {
            return response()->json(['message' => 'No rooms available for the selected dates.'], 409);
        }

        $totalAmount = $roomType->base_price * $nights;

        return DB::transaction(function () use ($validated, $hotel, $roomType, $availableRoom, $checkIn, $checkOut, $nights, $totalAmount) {
            // 1. Create or find guest
            $guest = Guest::withoutGlobalScopes()->firstOrCreate(
                ['email' => $validated['guest_email'], 'hotel_id' => $hotel->id],
                [
                    'hotel_id'   => $hotel->id,
                    'first_name' => explode(' ', $validated['guest_name'])[0],
                    'last_name'  => explode(' ', $validated['guest_name'])[1] ?? '',
                    'phone'      => $validated['guest_phone'] ?? null,
                ]
            );

            // 2. Create reservation (PENDING — payment not yet confirmed)
            $reservation = Reservation::withoutGlobalScopes()->create([
                'hotel_id'          => $hotel->id,
                'room_id'           => $availableRoom->id,
                'guest_id'          => $guest->id,
                'check_in_date'     => $checkIn,
                'check_out_date'    => $checkOut,
                'adults'            => $validated['adults'] ?? 1,
                'children'          => $validated['children'] ?? 0,
                'status'            => 'pending',
                'total_amount'      => $totalAmount,
                'special_requests'  => $validated['special_requests'] ?? null,
                'source'            => 'online_booking',
            ]);

            // 3. Create Folio for the reservation (financial ledger)
            $folio = Folio::withoutGlobalScopes()->create([
                'hotel_id'          => $hotel->id,
                'reservation_id'    => $reservation->id,
                'guest_id'          => $guest->id,
                'status'            => 'open',
                'currency'          => $hotel->group?->currency ?? 'USD',
            ]);

            // 4. Post room charge to folio (PENDING — awaits payment)
            FolioItem::withoutGlobalScopes()->create([
                'hotel_id'       => $hotel->id,
                'folio_id'       => $folio->id,
                'description'    => "Room Charge – {$nights} night(s) × {$roomType->name}",
                'amount'         => $totalAmount,
                'source'         => 'ROOM',
                'status'         => 'PENDING',
                'posted_at'      => now(),
                'audit_date'     => $checkIn,
            ]);

            // 5. Resolve payment gateway for frontend to initiate payment
            $paymentConfig = app(PaymentGatewayResolver::class)->publicConfig($hotel);

            return response()->json([
                'message'        => 'Reservation created. Complete payment to confirm.',
                'reservation_id' => $reservation->id,
                'folio_id'       => $folio->id,
                'amount'         => $totalAmount,
                'nights'         => $nights,
                'guest_email'    => $validated['guest_email'],
                'payment'        => $paymentConfig,
            ], 201);
        });
    }

    /**
     * POST /api/v1/booking/{hotel_slug}/confirm-payment
     * Called after payment provider webhook/callback confirms payment.
     * Updates reservation + folio to "confirmed/paid".
     */
    public function confirmPayment(Request $request, string $hotel_slug)
    {
        $validated = $request->validate([
            'reservation_id' => 'required|integer',
            'reference'      => 'required|string', // payment reference from gateway
        ]);

        $hotel = Hotel::withoutGlobalScopes()
            ->where('subdomain_slug', $hotel_slug)
            ->firstOrFail();

        $reservation = Reservation::withoutGlobalScopes()
            ->where('hotel_id', $hotel->id)
            ->where('id', $validated['reservation_id'])
            ->where('status', 'pending')
            ->firstOrFail();

        DB::transaction(function () use ($reservation, $validated) {
            // Confirm reservation
            $reservation->update([
                'status'             => 'confirmed',
                'payment_reference'  => $validated['reference'],
            ]);

            // Mark folio items as paid
            FolioItem::withoutGlobalScopes()
                ->where('folio_id', $reservation->folio?->id)
                ->where('status', 'PENDING')
                ->update(['status' => 'PAID']);
        });

        return response()->json([
            'message'        => 'Reservation confirmed successfully!',
            'reservation_id' => $reservation->id,
            'status'         => 'confirmed',
        ]);
    }
}
