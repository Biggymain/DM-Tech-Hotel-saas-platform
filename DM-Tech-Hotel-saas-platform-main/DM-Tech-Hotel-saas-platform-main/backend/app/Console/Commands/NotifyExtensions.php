<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class NotifyExtensions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pms:notify-extensions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify receptionist 24h before a guest check-out to confirm extension or departure.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tomorrow = now()->addDay()->toDateString();
        
        $reservations = \App\Models\Reservation::whereIn('status', ['checked_in', 'extended'])
            ->whereDate('check_out_date', $tomorrow)
            ->get();

        foreach ($reservations as $res) {
            \App\Models\Notification::create([
                'hotel_id' => $res->hotel_id,
                'title'    => 'Guest Stay Ending Tomorrow',
                'message'  => "Guest {$res->guest->full_name} is scheduled to check out tomorrow. Confirm extension or preparation.",
                'type'     => 'RESERVATION_EXPIRING',
                'priority' => 'medium',
                'data'     => ['reservation_id' => $res->id],
            ]);
        }

        $this->info("Successfully created " . $reservations->count() . " extension notifications.");
    }
}
