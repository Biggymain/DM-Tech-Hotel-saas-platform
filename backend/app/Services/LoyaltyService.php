<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\LoyaltyProduct;
use App\Models\LoyaltyTransaction;
use App\Models\Reservation;
use App\Models\HotelSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class LoyaltyService
{
    /**
     * Add points manually with a 500-point 30-day hard-cap.
     */
    public function addPoints(Guest $guest, int $points, string $reason, int $processedById): void
    {
        if ($points <= 0) {
            throw new Exception("Points must be positive.");
        }

        // Hard Guard: 500-point cap in 30-day window for manual adjustments
        $thirtyDaysAgo = now()->subDays(30);
        $manuallyAddedInWindow = LoyaltyTransaction::where('guest_id', $guest->id)
            ->where('type', 'manual_adjustment')
            ->where('points', '>', 0)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->sum('points');

        if (($manuallyAddedInWindow + $points) > 500) {
            $allowed = 500 - $manuallyAddedInWindow;
            throw new Exception("Manual point cap exceeded. Maximum allowed in this 30-day window: {$allowed} points.");
        }

        DB::transaction(function () use ($guest, $points, $reason, $processedById) {
            $guest->increment('loyalty_points', $points);

            LoyaltyTransaction::create([
                'hotel_id' => $guest->hotel_id,
                'guest_id' => $guest->id,
                'outlet_id' => null, // Admin Portal context
                'type' => 'manual_adjustment',
                'points' => $points,
                'reason' => $reason,
                'processed_by_id' => $processedById,
            ]);
        });
    }

    /**
     * Redeem a loyalty product with transactional safety and inventory bridge.
     */
    public function redeem(Guest $guest, int $productId): void
    {
        $product = LoyaltyProduct::findOrFail($productId);

        if (!$product->is_active) {
            throw new Exception("This loyalty product is no longer active.");
        }

        if ($guest->loyalty_points < $product->point_cost) {
            throw new Exception("Insufficient loyalty points. Cost: {$product->point_cost}");
        }

        DB::beginTransaction();
        try {
            // 1. Deduct points
            $guest->decrement('loyalty_points', $product->point_cost);

            // 2. Log transaction
            $transaction = LoyaltyTransaction::create([
                'hotel_id' => $guest->hotel_id,
                'guest_id' => $guest->id,
                'outlet_id' => $product->inventory_item_id ? $guest->hotel->outlets()->first()?->id : null, 
                'type' => 'redeem',
                'points' => -$product->point_cost,
                'reference_type' => get_class($product),
                'reference_id' => $product->id,
                'reason' => "Redemption: {$product->name}",
            ]);

            // 3. Inventory Bridge: If it's a physical item, deduct stock
            if ($product->inventory_item_id) {
                $inventoryService = app(InventoryService::class);
                // We use a dummy sourceId/sourceType for the inventory deduction log
                $inventoryService->deductStock(
                    $product->inventory_item_id,
                    1,
                    get_class($transaction),
                    $transaction->id
                );
            }

            // 4. Clock-Aware Service: Handle late checkout logic if applicable
            if ($product->type === 'service' && stripos($product->name, 'Late Checkout') !== false) {
                $this->processLateCheckoutRedemption($guest);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Redemption failed for Guest #{$guest->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Special logic for Late Checkout redemptions.
     */
    protected function processLateCheckoutRedemption(Guest $guest): void
    {
        $activeReservation = $guest->reservations()
            ->where('status', 'checked_in')
            ->first();

        if (!$activeReservation) {
            throw new Exception("No active checked-in reservation found for late checkout.");
        }

        if (!$this->isLateCheckoutAvailable($activeReservation)) {
            throw new Exception("Late checkout is unavailable due to a back-to-back booking.");
        }

        // Logic to update reservation check-out time (if we had a specific time field)
        // For now, we log the intent. In a real system, we'd update a 'checkout_hour' field.
        Log::info("Late checkout granted for Reservation #{$activeReservation->id}");
    }

    /**
     * Verify if late checkout is available (Back-to-Back check).
     */
    public function isLateCheckoutAvailable(Reservation $reservation): bool
    {
        $checkOutDate = $reservation->check_out_date->toDateString();
        
        foreach ($reservation->rooms as $room) {
            $hasOverlap = DB::table('reservation_rooms')
                ->join('reservations', 'reservation_rooms.reservation_id', '=', 'reservations.id')
                ->where('reservation_rooms.room_id', $room->id)
                ->where('reservations.id', '!=', $reservation->id)
                ->whereIn('reservations.status', ['pending', 'confirmed', 'checked_in'])
                ->where('reservations.check_in_date', '>=', $checkOutDate . ' 00:00:00')
                ->where('reservations.check_in_date', '<=', $checkOutDate . ' 23:59:59')
                ->exists();

            if ($hasOverlap) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get available products for a guest, with clock-aware gating for services.
     */
    public function getAvailableProducts(Guest $guest)
    {
        $products = LoyaltyProduct::where('hotel_id', $guest->hotel_id)
            ->where('is_active', true)
            ->get();

        $activeReservation = $guest->reservations()
            ->where('status', 'checked_in')
            ->first();

        return $products->filter(function ($product) use ($activeReservation) {
            // Clock-Aware Gating for "Late Checkout"
            if ($product->type === 'service' && stripos($product->name, 'Late Checkout') !== false) {
                if (!$activeReservation || !$this->isLateCheckoutAvailable($activeReservation)) {
                    return false;
                }
            }
            return true;
        })->values();
    }
}
