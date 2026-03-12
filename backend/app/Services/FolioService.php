<?php

namespace App\Services;

use App\Models\Folio;
use App\Models\FolioItem;
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
