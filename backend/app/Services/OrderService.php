<?php

namespace App\Services;

use App\Events\OrderFiredToStation;
use App\Events\OrderStatusUpdated;
use App\Models\Order;
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
            'pending'   => ['cooking', 'confirmed', 'served'],
            'confirmed' => ['cooking', 'served'],
            'cooking'   => ['ready'],
            'ready'     => ['served'],
        ];

        $currentStatus = $order->order_status ?: 'pending';

        if (!in_array($newStatus, $validTransitions[$currentStatus] ?? [])) {
            throw new \LogicException(
                "Invalid transition: {$currentStatus} → {$newStatus} for order #{$order->id}"
            );
        }

        $order->update(['order_status' => $newStatus]);

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
            event(new \App\Events\OrderServed($order));
        }

        Log::info("[OrderService] Order #{$order->id} status: {$currentStatus} → {$newStatus} (station: {$station})");

        return $order->fresh();
    }
}
