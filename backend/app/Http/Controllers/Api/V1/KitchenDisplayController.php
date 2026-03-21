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
        $tickets = KitchenTicket::with(['items', 'order', 'department'])
            ->whereNotIn('status', ['served', 'cancelled'])
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();
            
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

            $ticket->statusHistories()->create([
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'changed_by_user_id' => $request->user() ? $request->user()->id : null,
            ]);

            return response()->json($ticket->load('items'));
        });
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
