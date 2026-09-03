<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\KitchenTicket;
use App\Models\KitchenTicketItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessKitchenOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 600];

    /**
     * Create a new job instance.
     */
    public function __construct(public Order $order)
    {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // 1. Group items by kitchen_station_id
        // Load relationships to ensure we have station IDs
        $this->order->load(['items.menuItem']);

        $itemsByStation = $this->order->items->groupBy(function ($item) {
            return $item->menuItem?->kitchen_station_id ?? 0; // 0 = Unassigned/Main
        });

        DB::transaction(function () use ($itemsByStation) {
            foreach ($itemsByStation as $stationId => $items) {
                // 2. Create a KitchenTicket for each station
                $ticket = KitchenTicket::create([
                    'hotel_id' => $this->order->hotel_id,
                    'branch_id' => $this->order->branch_id ?? $this->order->hotel_id, // Default to hotel if branch is null
                    'order_id' => $this->order->id,
                    'outlet_id' => $this->order->outlet_id,
                    'kitchen_station_id' => $stationId ?: null,
                    'department_id' => $this->order->department_id ?: 1, // Fallback to default department
                    'ticket_number' => 'TCK-' . Str::upper(Str::random(6)),
                    'status' => 'queued',
                    'priority' => 0,
                ]);

                // 3. Add items to the ticket
                foreach ($items as $item) {
                    KitchenTicketItem::create([
                        'kitchen_ticket_id' => $ticket->id,
                        'menu_item_id' => $item->menu_item_id,
                        'item_name' => $item->menuItem?->name ?? 'Unknown Item',
                        'quantity' => $item->quantity,
                        'special_instructions' => $item->notes,
                        'status' => 'queued',
                    ]);
                }

                // 4. Trigger Real-Time Notification (Optional: Handled by Syncable or separate Event)
                // broadcast(new \App\Events\KitchenStatusUpdatedBroadcast($this->order->hotel_id, $ticket->toArray()));
            }
        });
    }
}
