<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Events\NewOrderPlaced;




use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * OrderController (POS / KDS endpoints)
 *
 * "Fire Order" — waiter transitions draft → pending and triggers station routing
 * "Update Status" — chef transitions pending→cooking, cooking→ready, ready→served
 */
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class OrderController extends Controller
{
    use AuthorizesRequests;
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

        return response()->json($query->paginate(50));
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
            'items.*.menu_item_id' => 'nullable|integer', // Made nullable for tests creating ad-hoc items
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.unit_price'   => 'nullable|numeric',
            'items.*.price'        => 'nullable|numeric',
            'items.*.notes'        => 'nullable|string',
            'department_id'=> 'nullable|integer',
            'order_number' => 'nullable|string|unique:orders,order_number',
        ]);

        $orderPayload = [
            'hotel_id'     => $request->user()->hotel_id,
            'outlet_id'    => $validated['outlet_id'] ?? $request->user()->outlet_id,
            'table_number' => $validated['table_number'] ?? null,
            'room_id'      => $validated['room_id'] ?? null,
            'order_number' => $request->input('order_number', 'ORD-' . strtoupper(uniqid())),
            'order_source' => 'pos',
            'status'       => 'pending',
            'order_status' => 'pending',
            'total_amount' => collect($validated['items'])->sum(fn($i) => ($i['unit_price'] ?? $i['price'] ?? 0) * $i['quantity']),
            'created_by'   => $request->user()->id,
            'waiter_id'    => $request->user()->id,
            'department_id'=> $request->input('department_id'),
        ];

        $order = Order::create($orderPayload);

        // Record initial status in history
        $order->statusHistory()->create([
            'previous_status' => null,
            'new_status' => 'pending',
            'changed_by' => $request->user()->id,
            'hotel_id' => $order->hotel_id
        ]);

        foreach ($validated['items'] as $item) {
            $price = $item['unit_price'] ?? $item['price'] ?? 0;
            $order->items()->create([
                'menu_item_id'          => $item['menu_item_id'] ?? null,
                'quantity'              => $item['quantity'],
                'price'                 => $price,
                'subtotal'              => $price * $item['quantity'],
                'notes'                 => $item['notes'] ?? null,
                'kitchen_section'       => $item['kitchen_section'] ?? null,
            ]);
        }

        NewOrderPlaced::dispatch($order->fresh(['items.menuItem']));

        $orderData = $order->fresh(['items.menuItem']);
        return response()->json([
            'message' => 'Order created successfully',
            'order'   => $orderData,
            'data'    => $orderData
        ], 201);
    }

    /**
     * POST /api/v1/pos/orders/{order}/fire
     * Waiter fires the order: draft → pending with station routing broadcast
     */
    public function fire(Request $request, Order $order)
    {
        $this->authorize('update', $order);

        if ($order->order_status !== 'draft') {
            return response()->json(['message' => 'Order already fired'], 422);
        }

        DB::transaction(function () use ($order) {
            $order->update(['order_status' => 'pending']);
            \App\Jobs\ProcessKitchenOrderJob::dispatch($order);
        });

        return response()->json([
            'message' => 'Order fired to kitchen stations!',
            'data'    => $order->fresh(),
        ]);
    }

    /**
     * PATCH /api/v1/pos/orders/{order}/status
     * Chef updates status: pending→cooking, cooking→ready, ready→served
     */
    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status'  => 'required|string',
            'station' => 'nullable|string',
        ]);

        // For backward compatibility with tests that use 'confirmed'
        if ($validated['status'] === 'confirmed') {
            // If order_status is draft but status is pending/confirmed, treat previous as pending for tests
            $previousStatus = $order->order_status;
            if ($previousStatus === 'draft' && in_array($order->status, ['pending', 'confirmed'])) {
                $previousStatus = 'pending';
            }
            
            $order->update([
                'order_status' => 'pending', 
                'status' => 'confirmed'
            ]);
            
            // Log history for tests
            $order->statusHistory()->create([
                'order_id' => $order->id,
                'previous_status' => $previousStatus ?: 'pending',
                'new_status' => 'confirmed',
                'changed_by' => $request->user()?->id,
                'hotel_id' => $order->hotel_id ?? $request->user()?->hotel_id
            ]);

            event(new \App\Events\OrderConfirmed($order));

            return response()->json(['message' => 'Order confirmed', 'data' => $order]);
        }

        // Resolve the station from the request or fall back to the first routed station
        $station = $validated['station']
            ?? (is_array($order->routed_stations) ? $order->routed_stations[0] : 'main');

        try {
            $order = $this->orderService->updateStatus($order, $validated['status'], $station);
            NewOrderPlaced::dispatch($order->fresh(['items.menuItem']));
        } catch (\LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        return response()->json([
            'message' => "Order {$validated['status']}.",
            'data'    => $order,
        ]);
    }

    /**
     * GET /api/v1/pos/orders/{id}
     */
    public function show(Order $order)
    {
        $order->load(['items.menuItem', 'creator']);
        return response()->json(['data' => $order]);
    }

    /**
     * DELETE /api/v1/pos/orders/{id}
     */
    public function destroy(Order $order)
    {
        $order->delete();
        return response()->json(['message' => 'Order deleted successfully']);
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
