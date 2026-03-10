<?php

namespace App\Http\Controllers\API\V1\PMS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Folio;
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
        $folios = Folio::with(['reservation.guest', 'items'])
            ->where('hotel_id', $request->user()->hotel_id)
            ->where('status', 'open')
            ->get();
            
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
}
