<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

/**
 * OrderController (POS / KDS endpoints)
 *
 * "Fire Order" — waiter transitions draft → pending and triggers station routing
 * "Update Status" — chef transitions pending→cooking, cooking→ready, ready→served
 */
class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    /**
     * GET /api/v1/pos/orders
     * List orders for the current outlet (or all for managers)
     */
    public function index(Request $request)
    {
        $query = Order::with(['items.menuItem', 'creator'])
            ->orderByDesc('created_at');

        if ($request->has('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }
        if ($request->has('order_status')) {
            $query->where('order_status', $request->order_status);
        }
        if ($request->has('station')) {
            $query->whereJsonContains('routed_stations', $request->station);
        }

        return response()->json(['data' => $query->paginate(50)]);
    }

    /**
     * POST /api/v1/pos/orders
     * Create a new order in 'draft' status (waiter is building it)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'outlet_id'    => 'required|integer',
            'table_number' => 'nullable|string',
            'room_id'      => 'nullable|integer',
            'items'        => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|integer',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.unit_price'   => 'required|numeric',
            'items.*.notes'        => 'nullable|string',
        ]);

        $order = Order::create([
            'hotel_id'     => $request->user()->hotel_id,
            'outlet_id'    => $validated['outlet_id'],
            'table_number' => $validated['table_number'] ?? null,
            'room_id'      => $validated['room_id'] ?? null,
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'order_source' => 'pos',
            'status'       => 'open',
            'order_status' => 'draft',
            'total_amount' => collect($validated['items'])->sum(fn($i) => $i['unit_price'] * $i['quantity']),
            'created_by'   => $request->user()->id,
            'waiter_id'    => $request->user()->id,
        ]);

        foreach ($validated['items'] as $item) {
            $order->items()->create([
                'menu_item_id'          => $item['menu_item_id'],
                'quantity'              => $item['quantity'],
                'unit_price'            => $item['unit_price'],
                'subtotal'              => $item['unit_price'] * $item['quantity'],
                'special_instructions'  => $item['notes'] ?? null,
            ]);
        }

        return response()->json(['data' => $order->fresh(['items.menuItem'])], 201);
    }

    /**
     * POST /api/v1/pos/orders/{order}/fire
     * Waiter fires the order: draft → pending with station routing broadcast
     */
    public function fire(Request $request, Order $order)
    {
        $this->authorize('update', $order); // Authorization policy

        try {
            $order = $this->orderService->fireOrder($order);
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Order fired to kitchen!',
            'data'    => $order,
        ]);
    }

    /**
     * PATCH /api/v1/pos/orders/{order}/status
     * Chef updates status: pending→cooking, cooking→ready, ready→served
     */
    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status'  => 'required|string|in:cooking,ready,served',
            'station' => 'nullable|string',
        ]);

        // Resolve the station from the request or fall back to the first routed station
        $station = $validated['station']
            ?? (is_array($order->routed_stations) ? $order->routed_stations[0] : 'main');

        try {
            $order = $this->orderService->updateStatus($order, $validated['status'], $station);
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Order {$validated['status']}.",
            'data'    => $order,
        ]);
    }

    /**
     * GET /api/v1/pos/kds
     * KDS-specific endpoint: returns pending/cooking/ready orders for a station/outlet
     */
    public function kds(Request $request)
    {
        $query = Order::with(['items.menuItem'])
            ->whereIn('order_status', ['pending', 'cooking', 'ready'])
            ->orderBy('created_at');

        if ($request->has('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }
        if ($request->has('station')) {
            $query->whereJsonContains('routed_stations', $request->station);
        }

        $orders = $query->get()->map(function ($order) {
            return [
                'id'           => $order->id,
                'order_number' => $order->order_number,
                'table_number' => $order->table_number,
                'room_id'      => $order->room_id,
                'order_status' => $order->order_status,
                'created_at'   => $order->created_at,
                'elapsed_mins' => (int) $order->created_at->diffInMinutes(now()),
                'items'        => $order->items->map(fn($i) => [
                    'name'     => $i->menuItem?->name ?? $i->item_name,
                    'quantity' => $i->quantity,
                    'notes'    => $i->special_instructions,
                    'station'  => $i->menuItem?->station_name ?? 'main',
                ]),
            ];
        });

        return response()->json(['data' => $orders]);
    }
}
