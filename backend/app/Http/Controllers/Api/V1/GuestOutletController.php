<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use Illuminate\Http\Request;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Outlet;
use App\Models\Order;
use App\Models\GuestPortalSession;
use Illuminate\Support\Facades\DB;

class GuestOutletController extends Controller
{
    /**
     * View outlet menu categories and items.
     */
    public function menu(Request $request, $outletId)
    {
        $session = $this->getActiveSession($request);

        $outlet = Outlet::where('hotel_id', $session->hotel_id)
            ->findOrFail($outletId);

        $categories = MenuCategory::where('hotel_id', $session->hotel_id)
            ->with(['items' => function($query) use ($outletId) {
                $query->where('outlet_id', $outletId)
                      ->where('is_active', true)
                      ->where('is_available', true);
            }])
            ->get();

        return response()->json([
            'outlet' => $outlet,
            'categories' => $categories
        ]);
    }

    /**
     * View a specific order for tracking.
     */
    public function showOrder(Request $request, $orderId)
    {
        $session = $this->getActiveSession($request);

        $order = Order::where('hotel_id', $session->hotel_id)
            ->where('room_id', $session->room_id)
            ->with(['items.menuItem', 'statusHistory'])
            ->findOrFail($orderId);

        return response()->json($order);
    }

    /**
     * Get smart menu recommendations.
     */
    public function recommendations(Request $request, $outletId)
    {
        $session = $this->getActiveSession($request);

        $outlet = Outlet::where('hotel_id', $session->hotel_id)
            ->findOrFail($outletId);

        // Simple recommendation logic: Top items or chef specials
        // In a real scenario, this would use a recommendation engine or history
        $recommendations = MenuItem::where('outlet_id', $outletId)
            ->where('is_active', true)
            ->where('is_available', true)
            ->where(function($query) {
                // Return items marked as popular or special (if columns exist) 
                // or just take some frequently ordered ones or just latest/featured
                $query->where('is_featured', true)
                      ->orWhere('price', '>', 0); // fallback
            })
            ->limit(5)
            ->get();

        return response()->json([
            'popular' => $recommendations->shuffle()->take(3),
            'chef_specials' => $recommendations->shuffle()->take(2),
            'perfect_pairings' => $recommendations->shuffle()->take(2),
        ]);
    }

    /**
     * Create an order from the guest portal.
     */
    public function storeOrder(Request $request, $outletId)
    {
        $session = $this->getActiveSession($request);

        $outlet = Outlet::where('hotel_id', $session->hotel_id)
            ->findOrFail($outletId);

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $session, $outlet) {
            $totalAmount = 0;
            $itemsToCreate = [];

            foreach ($validated['items'] as $itemData) {
                $menuItem = MenuItem::where('hotel_id', $session->hotel_id)
                    ->where('outlet_id', $outlet->id)
                    ->lockForUpdate()
                    ->findOrFail($itemData['menu_item_id']);

                $price = $menuItem->price;
                $totalAmount += $itemData['quantity'] * $price;

                $itemsToCreate[] = [
                    'menu_item_id' => $menuItem->id,
                    'quantity' => $itemData['quantity'],
                    'price' => $price,
                    'subtotal' => $price * $itemData['quantity'],
                    'notes' => $itemData['notes'] ?? null,
                    'kitchen_section' => $menuItem->department_id ?? null,
                ];
            }

            // Determine order source
            $orderSource = 'mobile';
            if ($session->context_type === 'room') {
                $orderSource = 'qr_room';
            } elseif ($session->context_type === 'table') {
                $orderSource = 'qr_table';
            }

            $order = Order::create([
                'hotel_id' => $session->hotel_id,
                'outlet_id' => $outlet->id,
                // Assume first department if outlet doesn't strictly own it, or use fallback
                'department_id' => \App\Models\Department::firstOrCreate(
                    ['hotel_id' => $session->hotel_id, 'name' => 'F&B'],
                    ['slug' => \Illuminate\Support\Str::slug('F&B')]
                )->id,
                'room_id' => $session->room_id,
                'table_number' => $session->context_type === 'outlet' 
                    ? ($session->context_data['table_number'] ?? null)
                    : ($session->context_type === 'table' ? $session->context_id : null),
                'order_number' => 'ORD-GUEST-' . strtoupper(uniqid()),
                'order_source' => $orderSource,
                'status' => 'pending',
                'total_amount' => $totalAmount,
                'payment_status' => 'unpaid',
                'payment_method' => null, // Up to gateway
                'created_by' => null, 
            ]);

            foreach ($itemsToCreate as $itemData) {
                $order->items()->create($itemData);
            }

            \App\Models\OrderStatusHistory::create([
                'order_id' => $order->id,
                'hotel_id' => $order->hotel_id,
                'previous_status' => null,
                'new_status' => 'pending',
                'changed_by' => null,
            ]);

            \App\Events\OrderCreated::dispatch($order);

            return response()->json([
                'message' => 'Order created successfully. Please proceed to payment.',
                'order' => $order->load('items')
            ], 201);
        });
    }

    private function getActiveSession(Request $request)
    {
        $token = $request->header('X-Guest-Session') ?? $request->session_token ?? $request->bearerToken();
        
        $session = GuestPortalSession::where('session_token', $token)
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        return $session;
    }
}
