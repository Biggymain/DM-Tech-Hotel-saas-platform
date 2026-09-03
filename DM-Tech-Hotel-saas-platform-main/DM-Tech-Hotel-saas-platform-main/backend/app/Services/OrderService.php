<?php

namespace App\Services;

use App\Events\OrderFiredToStation;
use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Models\LeisureBundle;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OrderService
 *
 * Central service for POS order lifecycle management.
 * Handles the "Fire Order" (draft→pending) and "Status Update" (cooking/ready/served) flows,
 * broadcasting WebSocket events to station-specific channels via Pusher/Laravel Echo.
 */
class OrderService
{
    public function __construct(private InventoryService $inventoryService) {}

    /**
     * Fire an order to the kitchen.
     * Transitions: draft → pending
     * Broadcasts: OrderFiredToStation to every unique station found in the order items
     *
     * @param Order $order  Must be in 'draft' state
     */
    public function fireOrder(Order $order): Order
    {
        if ($order->order_status !== 'draft') {
            throw new \LogicException("Order #{$order->id} is already fired (status: {$order->order_status}).");
        }

        // Load items with their menu item's station_name
        $order->loadMissing(['items.menuItem']);

        // Group items by station
        $stationGroups = [];
        foreach ($order->items as $item) {
            $station = $item->menuItem?->station_name ?? 'main';
            $stationGroups[$station][] = [
                'name'        => $item->menuItem?->name ?? $item->item_name,
                'quantity'    => $item->quantity,
                'notes'       => $item->special_instructions ?? null,
                'station'     => $station,
            ];
        }

        $routedStations = array_keys($stationGroups);

        DB::transaction(function () use ($order, $stationGroups, $routedStations) {
            $order->update([
                'order_status'   => 'pending',
                'routed_stations' => $routedStations,
            ]);

            // Broadcast to each station's private channel
            foreach ($stationGroups as $station => $items) {
                broadcast(new OrderFiredToStation($order, $station, $items))->toOthers();
                Log::info("[OrderService] Fired order #{$order->id} to station '{$station}' ({$order->hotel_id})");
            }
        });

        return $order->fresh();
    }

    /**
     * Update an order's status (by the chef on the KDS).
     * Transitions: pending → cooking → ready → served
     * Broadcasts: OrderStatusUpdated to both station and waiter channels
     *
     * @param Order  $order
     * @param string $newStatus  'cooking' | 'ready' | 'served'
     * @param string $station    The station performing this update
     */
    public function updateStatus(Order $order, string $newStatus, string $station): Order
    {
        $validTransitions = [
            'pending'   => ['cooking', 'confirmed', 'served', 'voided'],
            'confirmed' => ['cooking', 'served', 'voided'],
            'cooking'   => ['ready', 'voided'],
            'ready'     => ['served', 'voided'],
        ];

        $currentStatus = $order->order_status ?: 'pending';

        if (!in_array($newStatus, $validTransitions[$currentStatus] ?? [])) {
            throw new \LogicException(
                "Invalid transition: {$currentStatus} → {$newStatus} for order #{$order->id}"
            );
        }

        $updateData = ['order_status' => $newStatus];
        if ($newStatus === 'served') {
            $updateData['served_at'] = now();
        }

        $order->update($updateData);

        \App\Models\OrderStatusHistory::create([
            'order_id' => $order->id,
            'hotel_id' => $order->hotel_id,
            'previous_status' => $currentStatus,
            'new_status' => $newStatus,
            'changed_by' => auth()->id() ?? $order->created_by,
        ]);

        // Broadcast to station (chef's KDS update) + possibly waiter (on 'ready')
        broadcast(new OrderStatusUpdated($order, $newStatus, $station))->toOthers();

        // Dispatch integration events for inventory/billing
        if ($newStatus === 'confirmed') {
            event(new \App\Events\OrderConfirmed($order));
        } elseif ($newStatus === 'served') {
            // "Drink-Check" mandatory requirement (Port 3003 Leisure Hub)
            $order->loadMissing('items.menuItem');
            foreach ($order->items as $item) {
                $bundle = LeisureBundle::where('menu_item_id', $item->menu_item_id)->first();
                if ($bundle) {
                    try {
                        // Resolve the specific inventory item for this outlet
                        $templateItem = \App\Models\InventoryItem::findOrFail($bundle->inventory_item_id);
                        $outletItem = $this->inventoryService->resolveItemForOutlet($templateItem, $order->outlet_id);

                        $this->inventoryService->deductStock(
                            $outletItem->id, 
                            $bundle->quantity * $item->quantity, 
                            get_class($order), 
                            $order->id
                        );
                    } catch (\Exception $e) {
                         // calibration: "No Pool Pass can be 'Served' without a linked InventoryItem deduction."
                         \Illuminate\Support\Facades\Log::error("[Drink-Check] Failure for order #{$order->id}: " . $e->getMessage());
                         throw new \LogicException("Cannot serve Pool Pass: Drink inventory ({$templateItem->name}) is empty.");
                    }
                }
            }
            event(new \App\Events\OrderServed($order));
        }

        Log::info("[OrderService] Workflow stepping to: {$newStatus} for order #{$order->id} (station: {$station})");

        return $order->fresh();
    }

    /**
     * Void an order and notify relevant systems (KDS, Audit, Inventory).
     */
    public function voidOrder(Order $order, string $reason, ?int $userId = null): Order
    {
        $currentStatus = $order->order_status ?: 'pending';
        $hotelId = $order->hotel_id;
        $userId = $userId ?? auth()->id() ?? $order->created_by;

        DB::transaction(function () use ($order, $reason, $currentStatus, $hotelId, $userId) {
            $order->update([
                'order_status' => 'voided',
                'void_reason'  => $reason
            ]);

            // 1. SIEM High-Severity Audit Log
            // Triggers Score 12 automatically in AuditLogObserver
            \App\Services\AuditLogService::log(
                get_class($order), $order->id, 'order_voided',
                ['status' => $currentStatus], ['status' => 'voided', 'reason' => $reason],
                $reason, 'api', $hotelId, $userId
            );

            // 2. KDS Notification
            // If ticket exists and is being prepared, notify stations to stop
            $tickets = \App\Models\KitchenTicket::where('order_id', $order->id)
                ->whereIn('status', ['pending', 'preparing', 'cooking'])
                ->get();

            foreach ($tickets as $ticket) {
                $ticket->update(['status' => 'voided']);
                
                // Get station name from relationship or fallback
                $stationName = $ticket->kitchenStation?->name ?? 'main';
                
                broadcast(new \App\Events\OrderStatusUpdated($order, 'voided', $stationName))->toOthers();
            }

            // 3. Security Kill-Switch
            if (stripos($reason, 'Security') !== false) {
                $sessionToken = request()->cookie('guest_session') ?? request()->header('X-Guest-Session');
                if ($sessionToken) {
                    app(\App\Services\SessionSentryService::class)->revoke($sessionToken);
                } else {
                    $session = \App\Models\GuestPortalSession::where('context_type', 'order')
                        ->where('context_id', $order->id)
                        ->where('status', '!=', 'revoked')
                        ->first();
                    if ($session) {
                        app(\App\Services\SessionSentryService::class)->revoke($session->id);
                    }
                }
            }
        });

        return $order->fresh();
    }
}
