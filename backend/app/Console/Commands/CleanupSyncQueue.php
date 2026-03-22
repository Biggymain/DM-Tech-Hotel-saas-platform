<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SyncQueue;

class CleanupSyncQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removes old completed sync records proactively avoiding manual storage bloat inherently mapping exclusively to isolated offline configurations.';

    /**
     * Execute the console command natively evaluating completed lifecycles.
     */
    public function handle()
    {
        // Add cleanup strategy deleting completely synced trackers older than 7 days dynamically 
        $deleted = SyncQueue::where('status', 'completed')
            ->where('synced_at', '<', now()->subDays(7))
            ->delete();

        $this->info("Cleaned up {$deleted} strictly completed legacy sync tracking records.");
    }
}
