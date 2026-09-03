<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\StaffDailyPin;
use App\Models\StockTransfer;
use App\Events\Inventory\StockTransferInitiated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Exception;

class StockTransferService
{
    public function __construct(private InventoryService $inventoryService) {}

    /**
     * Initiate a stock transfer request.
     */
    public function initiateTransfer(int $itemId, float $quantity, int $fromOutlet, int $toOutlet, int $userId): StockTransfer
    {
        return DB::transaction(function () use ($itemId, $quantity, $fromOutlet, $toOutlet, $userId) {
            $item = InventoryItem::findOrFail($itemId);

            $transfer = StockTransfer::create([
                'hotel_id' => $item->hotel_id,
                'inventory_item_id' => $itemId,
                'from_location_id' => $fromOutlet,
                'to_location_id' => $toOutlet,
                'quantity_requested' => $quantity,
                'requested_by' => $userId,
                'status' => 'pending',
            ]);

            event(new StockTransferInitiated($transfer));

            return $transfer;
        });
    }

    /**
     * Dispatch the stock, moving it to transit and deducting from source.
     */
    public function dispatchTransfer(int $transferId, int $userId, float $quantity): StockTransfer
    {
        return DB::transaction(function () use ($transferId, $userId, $quantity) {
            $transfer = StockTransfer::lockForUpdate()->findOrFail($transferId);

            if ($transfer->status !== 'pending') {
                throw new Exception("Transfer cannot be dispatched in current status: {$transfer->status}");
            }

            $transfer->update([
                'quantity_dispatched' => $quantity,
                'dispatched_by' => $userId,
                'dispatched_at' => now(),
                'status' => 'in_transit',
            ]);

            // Deduct from source immediately
            $this->inventoryService->deductStock(
                $transfer->inventory_item_id,
                $quantity,
                get_class($transfer),
                $transfer->id
            );

            return $transfer;
        });
    }

    /**
     * Accept (Handshake) the stock at destination.
     */
    public function acceptTransfer(int $transferId, int $waiterId, string $waiterPin, float $receivedQuantity): StockTransfer
    {
        // 1. PIN Wall: First line of defense
        $dailyPin = StaffDailyPin::where('user_id', $waiterId)
            ->where('expires_at', '>', now())
            ->first();

        if (!$dailyPin || !Hash::check($waiterPin, $dailyPin->pin_hash)) {
            $this->logFailedHandshake($transferId, $waiterId);
            throw new Exception("Invalid or expired Daily PIN. Handshake failed.", 403);
        }

        return DB::transaction(function () use ($transferId, $waiterId, $receivedQuantity) {
            $transfer = StockTransfer::lockForUpdate()->findOrFail($transferId);

            if ($transfer->status !== 'in_transit') {
                throw new Exception("Transfer must be in_transit to be accepted.");
            }

            $isDisputed = $receivedQuantity < $transfer->quantity_dispatched;
            $newStatus = $isDisputed ? 'disputed' : 'completed';

            $transfer->update([
                'quantity_received' => $receivedQuantity,
                'received_by' => $waiterId,
                'received_at' => now(),
                'status' => $newStatus,
            ]);

            // Liability Shift: Increase destination stock
            $sourceItem = $transfer->item;
            $destinationItem = $this->inventoryService->resolveItemForOutlet($sourceItem, $transfer->to_location_id);
            
            $destinationItem->increment('current_stock', $receivedQuantity);

            // Transaction log for destination
            InventoryTransaction::create([
                'hotel_id' => $transfer->hotel_id,
                'outlet_id' => $transfer->to_location_id,
                'inventory_item_id' => $destinationItem->id,
                'type' => 'in',
                'quantity' => $receivedQuantity,
                'reference_type' => get_class($transfer),
                'reference_id' => $transfer->id,
                'notes' => "Stock Transfer Received via PIN Handshake [Status: {$newStatus}]",
                'created_by_user_id' => $waiterId,
            ]);

            // High-Severity Logic for Disputes
            if ($isDisputed) {
                AuditLog::create([
                    'hotel_id' => $transfer->hotel_id,
                    'user_id' => $waiterId,
                    'change_type' => 'stock_transfer_dispute',
                    'entity_type' => get_class($transfer),
                    'entity_id' => $transfer->id,
                    'old_values' => ['dispatched' => $transfer->quantity_dispatched],
                    'new_values' => ['received' => $receivedQuantity],
                    'reason' => "Shortage detected during stock handshake (#{$transfer->id})",
                    'severity_score' => 12, // High-severity as per specs
                    'source' => 'api',
                ]);

                Log::channel('siem')->warning('Stock Transfer Dispute - Shortage Detected', [
                    'severity_score' => 12,
                    'transfer_id' => $transfer->id,
                    'dispatched' => $transfer->quantity_dispatched,
                    'received' => $receivedQuantity,
                    'staff_id' => $waiterId,
                ]);
            }

            return $transfer;
        });
    }

    protected function logFailedHandshake(int $transferId, int $userId): void
    {
        AuditLog::create([
            'user_id' => $userId,
            'change_type' => 'STOCK_RECEIVE_FAILED_PIN',
            'entity_type' => StockTransfer::class,
            'entity_id' => $transferId,
            'reason' => "Invalid Daily PIN attempt for stock reception (#{$transferId})",
            'severity_score' => 5,
            'source' => 'api',
        ]);
    }
}
