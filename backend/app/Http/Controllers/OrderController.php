<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $hotelId = $request->user()->hotel_id;
        $orders = Order::where('hotel_id', $hotelId)
            ->with(['items', 'creator', 'statusHistory.user'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $hotelId = $request->user()->hotel_id;

        $validated = $request->validate([
            'outlet_id' => 'required|exists:outlets,id',
            'department_id' => 'required|exists:departments,id',
            'room_id' => 'nullable|exists:rooms,id',
            'table_number' => 'nullable|string',
            'order_number' => [
                'required',
                'string',
                Rule::unique('orders')->where(function ($query) use ($hotelId) {
                    return $query->where('hotel_id', $hotelId);
                })
            ],
            'order_source' => 'required|in:pos,qr_table,qr_room,room_service,mobile',
            'payment_method' => 'nullable|in:cash,card,room_charge,manual,online_gateway',
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'nullable|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
            'items.*.kitchen_section' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $hotelId, $request) {
            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $totalAmount += $item['quantity'] * $item['price'];
            }

            $order = Order::create([
                'hotel_id' => $hotelId,
                'outlet_id' => $validated['outlet_id'],
                'department_id' => $validated['department_id'],
                'room_id' => $validated['room_id'] ?? null,
                'table_number' => $validated['table_number'] ?? null,
                'order_number' => $validated['order_number'],
                'order_source' => $validated['order_source'],
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'payment_status' => 'unpaid',
                'payment_method' => $validated['payment_method'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['items'] as $item) {
                $order->items()->create([
                    'menu_item_id' => $item['menu_item_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'notes' => $item['notes'] ?? null,
                    'kitchen_section' => $item['kitchen_section'] ?? null,
                ]);
            }

            // Write to Status History audit line
            $order->statusHistory()->create([
                'previous_status' => null,
                'new_status' => 'pending',
                'changed_by' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Order created successfully',
                'order' => $order->load('items')
            ], 201);
        });
    }

    public function show(Request $request, $id)
    {
        $hotelId = $request->user()->hotel_id;
        $order = Order::where('hotel_id', $hotelId)
            ->with(['items', 'creator', 'statusHistory.user'])
            ->findOrFail($id);

        return response()->json($order);
    }

    public function updateStatus(Request $request, $id)
    {
        $hotelId = $request->user()->hotel_id;
        $order = Order::where('hotel_id', $hotelId)->findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,preparing,ready,served,closed,cancelled'
        ]);

        $newStatus = $validated['status'];

        // Enforce basic logical workflow constraints (simplified for example)
        if ($order->status === 'closed' || $order->status === 'cancelled') {
            return response()->json(['message' => 'Cannot change status of a closed or cancelled order.'], 400);
        }

        return DB::transaction(function () use ($order, $newStatus, $request) {
            $previousStatus = $order->status;
            
            $order->update(['status' => $newStatus]);

            $order->statusHistory()->create([
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'changed_by' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Order status updated',
                'order' => $order
            ]);
        });
    }

    public function destroy(Request $request, $id)
    {
        $hotelId = $request->user()->hotel_id;
        $order = Order::where('hotel_id', $hotelId)->findOrFail($id);

        // Instead of hard delete, usually we just cancel.
        // Let's implement destroy as standard REST hard delete as per plan, 
        // normally only possible if pending.
        
        if (!in_array($order->status, ['pending', 'cancelled'])) {
            return response()->json(['message' => 'Can only delete pending or cancelled orders.'], 400);
        }

        $order->delete();
        
        return response()->json(['message' => 'Order deleted successfully']);
    }
}
