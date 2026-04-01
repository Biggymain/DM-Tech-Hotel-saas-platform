<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KitchenDisplayController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = KitchenTicket::with(['items', 'order', 'department'])
            ->whereNotIn('status', ['served', 'cancelled'])
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc');

        // Strict Branch + Station Isolation
        if (!$user->is_super_admin) {
            $query->where('branch_id', $user->branch_id ?? $user->hotel_id);
            
            if ($user->kitchen_station_id) {
                $query->where('kitchen_station_id', $user->kitchen_station_id);
            }
        }
            
        $threshold = config('hotel.checkout_alert_threshold', 2);

        $tickets = $query->get()->map(function ($ticket) use ($threshold) {
            $ticket->checkout_alert = false;
            
            if ($ticket->order && $ticket->order->room_id) {
                $reservation = \App\Models\Reservation::where('room_id', $ticket->order->room_id)
                    ->where('status', 'checked_in')
                    ->orderBy('check_out_date', 'asc')
                    ->first();
                    
                if ($reservation && $reservation->check_out_date) {
                    // Assuming standard 12:00 PM checkout time for the date
                    $checkoutTime = \Carbon\Carbon::parse($reservation->check_out_date)->setHour(12)->setMinute(0);
                    
                    if (now()->diffInHours($checkoutTime, false) <= $threshold && now()->diffInHours($checkoutTime, false) >= -24) {
                        $ticket->checkout_alert = true;
                    }
                }
            }
            return $ticket;
        });

        return response()->json($tickets);
    }

    public function show(Request $request, $id)
    {
        $ticket = KitchenTicket::with(['items.menuItem', 'order', 'department'])->findOrFail($id);
            
        return response()->json($ticket);
    }

    public function updateStatus(Request $request, $id)
    {
        $ticket = KitchenTicket::findOrFail($id);
        
        $validated = $request->validate([
            'status' => 'required|in:queued,accepted,preparing,ready,served'
        ]);

        return DB::transaction(function () use ($ticket, $validated, $request) {
            $previousStatus = $ticket->status;
            $newStatus = $validated['status'];
            
            $ticket->update(['status' => $newStatus]);
            
            if ($newStatus === 'preparing' && !$ticket->started_at) {
                $ticket->update(['started_at' => now()]);
            }
            
            if ($newStatus === 'ready' && !$ticket->completed_at) {
                $ticket->update(['completed_at' => now()]);
            }

            $ticket->statusHistories()->create([
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'changed_by_user_id' => $request->user() ? $request->user()->id : null,
            ]);

            // Broadcast real-time update
            broadcast(new \App\Events\KitchenTicketStatusUpdated($ticket, $newStatus))->toOthers();

            return response()->json($ticket->load('items'));
        });
    }

    public function toggleAvailability(Request $request, $id)
    {
        $item = \App\Models\MenuItem::findOrFail($id);
        $item->update(['is_available' => !$item->is_available]);

        return response()->json(['success' => true, 'is_available' => $item->is_available]);
    }

    public function requestRestock(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'menu_item_id' => 'required|exists:menu_items,id',
            'kitchen_station_id' => 'required|exists:kitchen_stations,id',
            'notes' => 'nullable|string',
        ]);

        $restock = \App\Models\RestockRequest::create([
            ...$validated,
            'hotel_id' => $user->hotel_id,
            'branch_id' => $user->branch_id ?? $user->hotel_id,
            'requested_by' => $user->id,
            'status' => 'pending',
        ]);

        return response()->json($restock, 201);
    }

    public function updateItemStatus(Request $request, $id)
    {
        $item = KitchenTicketItem::findOrFail($id);
        
        $validated = $request->validate([
            'status' => 'required|in:queued,preparing,ready,served'
        ]);

        $item->update(['status' => $validated['status']]);

        return response()->json($item);
    }
}
