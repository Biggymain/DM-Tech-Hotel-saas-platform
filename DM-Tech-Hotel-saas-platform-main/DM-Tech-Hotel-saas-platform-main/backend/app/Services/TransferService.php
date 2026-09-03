<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\StaffDailyPin;
use App\Models\TransferLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TransferService
{
    /**
     * Transfer items to a target staff member, requiring their PIN for validation.
     * 
     * @param array $itemIds List of OrderItem IDs to transfer.
     * @param int $sourceStaffId ID of the staff initiating the transfer.
     * @param int $targetStaffId ID of the staff receiving the items.
     * @param string $targetStaffPin PIN of the receiving staff.
     * @param int|null $targetSessionId Optional TableSession ID to move the items to.
     * @param string|null $reason Optional reason for the transfer.
     * @return array Array of transferred items or throws Exception.
     */
    public function transferItems(
        array $itemIds,
        int $sourceStaffId,
        int $targetStaffId,
        string $targetStaffPin,
        ?int $targetSessionId = null,
        ?string $reason = null
    ): array {
        // 1. PIN Validation & Handshake Security
        $dailyPin = StaffDailyPin::where('user_id', $targetStaffId)
            ->where('expires_at', '>', now())
            ->first();

        if (!$dailyPin || !Hash::check($targetStaffPin, $dailyPin->pin_hash)) {
            $this->logFailedHandshake($targetStaffId, $sourceStaffId);
            throw new \Exception('Invalid PIN for target staff. Handshake failed.', 403);
        }

        // Reset failed handshake counter on success
        Cache::forget("failed_handshakes_{$targetStaffId}");

        $transferredItems = [];

        DB::transaction(function () use (
            $itemIds, $sourceStaffId, $targetStaffId, $targetSessionId, $reason, &$transferredItems
        ) {
            foreach ($itemIds as $itemId) {
                // Fetch the item
                $item = OrderItem::lockForUpdate()->findOrFail($itemId);

                // Critical Security Constraint:
                if (in_array($item->status, ['paid', 'voided', 'returned'])) {
                    throw new \Exception("Cannot transfer item {$item->id} because it is {$item->status}.", 422);
                }

                $sourceSessionId = $item->table_session_id;

                // Create the transfer log
                TransferLog::create([
                    'hotel_id' => current_hotel_id() ?? $item->order->hotel_id,
                    'order_item_id' => $item->id,
                    'source_staff_id' => $sourceStaffId,
                    'target_staff_id' => $targetStaffId,
                    'source_session_id' => $sourceSessionId,
                    'target_session_id' => $targetSessionId ?? $sourceSessionId,
                    'status' => 'success',
                    'reason' => $reason,
                ]);

                // Shift Liability
                $item->waiter_id = $targetStaffId;
                if ($targetSessionId !== null) {
                    $item->table_session_id = $targetSessionId;
                }
                $item->save();

                $transferredItems[] = $item;
            }
        });

        // Trigger Event (Optional depending on setup, but typically good for KDS sync)
        if (class_exists(\App\Events\ItemsTransferred::class)) {
            event(new \App\Events\ItemsTransferred($transferredItems, $sourceStaffId, $targetStaffId));
        }

        return $transferredItems;
    }

    /**
     * Track and log failed handshake attempts.
     */
    protected function logFailedHandshake(int $targetStaffId, int $sourceStaffId): void
    {
        $cacheKey = "failed_handshakes_{$targetStaffId}";
        $attempts = Cache::increment($cacheKey);

        if ($attempts >= 3) {
            AuditLogService::log(
                'user',
                $targetStaffId,
                'failed_handshake',
                null,
                null,
                "Failed handshake via PIN mismatch. Attempts: {$attempts}. Initiator: {$sourceStaffId}.",
                'api',
                current_hotel_id(),
                $targetStaffId
            );
        }
    }
}

function current_hotel_id() {
    return session('active_hotel_id') ?? (app()->bound('active_hotel_id') ? app('active_hotel_id') : (app()->bound('tenant_id') ? app('tenant_id') : null));
}
