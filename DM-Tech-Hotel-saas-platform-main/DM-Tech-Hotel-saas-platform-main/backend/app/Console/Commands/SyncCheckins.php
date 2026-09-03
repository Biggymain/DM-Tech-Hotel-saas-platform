<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reservation;

class SyncCheckins extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pms:sync-checkin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically transition confirmed reservations to checked_in on their arrival date.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = now()->toDateString();
        
        $count = Reservation::where('status', 'confirmed')
            ->whereDate('check_in_date', '<=', $today)
            ->update(['status' => 'checked_in']);

        $this->info("Successfully synchronized {$count} check-ins.");
    }
}
