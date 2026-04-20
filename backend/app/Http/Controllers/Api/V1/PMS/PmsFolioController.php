<?php

namespace App\Http\Controllers\Api\V1\PMS;
use App\Http\Controllers\Controller;


use Illuminate\Http\Request;
use App\Models\Folio;
use App\Models\Reservation;
use App\Services\FolioService;

class PmsFolioController extends Controller
{
    protected $folioService;

    public function __construct(FolioService $folioService)
    {
        $this->folioService = $folioService;
    }

    /**
     * List all open folios for the hotel.
     */
    public function index(Request $request)
    {
        $folios = Folio::with(['reservation.guest', 'items', 'payments'])
            ->where('hotel_id', $request->user()->hotel_id)
            ->where('status', 'open')
            ->get();
            
        foreach ($folios as $folio) {
            $this->verifyFolioIntegrity($folio);
        }

        // Filter out any that were just suspended
        $folios = $folios->filter(fn($f) => $f->status === 'open')->values();

        return response()->json(['success' => true, 'data' => $folios]);
    }

    /**
     * Post a charge to a specific folio.
     */
    public function postCharge(Request $request, Folio $folio)
    {
        if ($folio->hotel_id !== $request->user()->hotel_id) {
            abort(403, 'Unauthorized access.');
        }

        if ($folio->status !== 'open') {
            abort(422, 'Cannot post charges to a closed folio.');
        }

        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'attachable_type' => 'nullable|string',
            'attachable_id' => 'nullable|integer',
        ]);

        $item = $this->folioService->addCharge(
            $folio,
            $request->description,
            $request->amount,
            $request->attachable_type,
            $request->attachable_id
        );

        return response()->json([
            'success' => true,
            'data' => $item,
            'message' => 'Charge posted successfully.',
            'folio_balance' => $folio->fresh()->balance
        ]);
    }

    /**
     * Post a payment to a specific folio.
     */
    public function postPayment(Request $request, Folio $folio)
    {
        if ($folio->hotel_id !== $request->user()->hotel_id) {
            abort(403, 'Unauthorized access.');
        }

        if ($folio->status !== 'open') {
            abort(422, 'Cannot post payments to a closed folio.');
        }

        $request->validate([
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'attachable_type' => 'nullable|string',
            'attachable_id' => 'nullable|integer',
        ]);

        $item = $this->folioService->addPayment(
            $folio,
            $request->description,
            $request->amount,
            $request->attachable_type,
            $request->attachable_id
        );

        return response()->json([
            'success' => true,
            'data' => $item,
            'message' => 'Payment posted successfully.',
            'folio_balance' => $folio->fresh()->balance
        ]);
    }

    /**
     * Get the folio for a specific reservation.
     */
    public function showByReservation(Request $request, Reservation $reservation)
    {
        if ($reservation->hotel_id !== $request->user()->hotel_id) {
            abort(403, 'Unauthorized access.');
        }

        $folio = Folio::with(['items', 'reservation.guest', 'payments'])
            ->where('reservation_id', $reservation->id)
            ->firstOrFail();

        $this->verifyFolioIntegrity($folio);

        return response()->json(['success' => true, 'data' => $folio]);
    }

    /**
     * Internal: Re-verify all JWS receipts for this folio.
     */
    private function verifyFolioIntegrity(Folio $folio)
    {
        if ($folio->status === 'suspended_tamper_detected') {
            return;
        }

        $payments = $folio->payments;
        foreach ($payments as $payment) {
            if (!\App\Services\ReceiptTokenGuard::verifyToken($payment)) {
                $folio->update(['status' => 'suspended_tamper_detected']);

                \App\Models\AuditLog::create([
                    'hotel_id' => $folio->hotel_id,
                    'change_type' => 'security_alert',
                    'entity_type' => 'folio',
                    'entity_id' => $folio->id,
                    'source' => 'jws_shield',
                    'reason' => "Tamper detected! JWS Token verification failed for PaymentTransaction #{$payment->id}.",
                    'new_values' => ['payment_transaction_id' => $payment->id, 'severity' => 20]
                ]);

                abort(403, 'TAMPER DETECTED: Financial ledger seal broken. Folio suspended. SIEM Alert dispatched.');
            }
        }
    }
}
