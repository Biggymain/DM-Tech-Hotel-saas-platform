<?php

namespace App\Services;

use App\Models\Folio;
use App\Models\FolioItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FolioService
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Add a charge to a Folio.
     * Atomic: rolls back if inventory deduction fails.
     */
    public function addCharge(Folio $folio, string $description, float $amount, ?string $attachableType = null, ?int $attachableId = null, string $source = 'ROOM', ?int $inventoryItemId = null)
    {
        // Validate active reservation
        if ($folio->reservation->status !== 'checked_in') {
            throw new \Exception("Cannot post charge: Reservation is not in 'checked_in' status.");
        }

        // Disable 'Charge to Room' 5 hours before check-out (Check-out is strictly 12:00 PM)
        $checkOutDate = Carbon::parse($folio->reservation->check_out_date);
        $restrictionTime = $checkOutDate->copy()->startOfDay()->addHours(7); // 12 PM - 5 hours = 7 AM
        
        if (now()->isAfter($restrictionTime)) {
             throw new \Exception("Cannot post charge: 'Charge to Room' is disabled for this guest as they are within 5 hours of 12 PM check-out.");
        }

        return DB::transaction(function () use ($folio, $description, $amount, $attachableType, $attachableId, $source, $inventoryItemId) {
            $item = FolioItem::create([
                'folio_id' => $folio->id,
                'hotel_id' => $folio->hotel_id,
                'attachable_type' => $attachableType,
                'attachable_id' => $attachableId,
                'description' => $description,
                'amount' => $amount,
                'is_charge' => true,
                'source' => $source,
                'status' => 'PAID', // Default to PAID for POS/Room charges
                'inventory_item_id' => $inventoryItemId,
            ]);

            // Atomic inventory deduction if applicable
            if ($inventoryItemId && $source === 'POS') {
                $this->inventoryService->deductStock($inventoryItemId, 1, 'folio_item', $item->id);
            }

            $this->recalculateFolio($folio);

            return $item;
        });
    }

    /**
     * Add a payment (credit) to a Folio.
     */
    public function addPayment(Folio $folio, string $description, float $amount, ?string $attachableType = null, ?int $attachableId = null)
    {
        return DB::transaction(function () use ($folio, $description, $amount, $attachableType, $attachableId) {
            $item = FolioItem::create([
                'folio_id' => $folio->id,
                'hotel_id' => $folio->hotel_id,
                'attachable_type' => $attachableType,
                'attachable_id' => $attachableId,
                'description' => $description,
                'amount' => $amount,
                'is_charge' => false,
                'status' => 'PAID',
            ]);

            $this->recalculateFolio($folio);

            return $item;
        });
    }

    /**
     * Get aggregated folio data for a reservation.
     */
    public function getGuestFolio(int $reservationId)
    {
        return Folio::with(['items', 'reservation.guest'])
            ->where('reservation_id', $reservationId)
            ->first();
    }

    /**
     * Recalculate and update the totals and balance of a Folio.
     */
    private function recalculateFolio(Folio $folio)
    {
        $charges = $folio->items()->where('is_charge', true)->sum('amount');
        $payments = $folio->items()->where('is_charge', false)->sum('amount');

        $folio->update([
            'total_charges' => $charges,
            'total_payments' => $payments,
            'balance' => $charges - $payments,
        ]);

        if (class_exists(\App\Events\GuestFolioUpdated::class)) {
            event(new \App\Events\GuestFolioUpdated($folio->reservation_id));
        }
    }
}
