<?php

namespace App\Services;

use App\Models\Folio;
use App\Models\FolioItem;
use Illuminate\Support\Facades\DB;

class FolioService
{
    /**
     * Add a charge to a Folio.
     */
    public function addCharge(Folio $folio, string $description, float $amount, ?string $attachableType = null, ?int $attachableId = null)
    {
        return DB::transaction(function () use ($folio, $description, $amount, $attachableType, $attachableId) {
            $item = FolioItem::create([
                'folio_id' => $folio->id,
                'attachable_type' => $attachableType,
                'attachable_id' => $attachableId,
                'description' => $description,
                'amount' => $amount,
                'is_charge' => true,
            ]);

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
                'attachable_type' => $attachableType,
                'attachable_id' => $attachableId,
                'description' => $description,
                'amount' => $amount,
                'is_charge' => false,
            ]);

            $this->recalculateFolio($folio);

            return $item;
        });
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
