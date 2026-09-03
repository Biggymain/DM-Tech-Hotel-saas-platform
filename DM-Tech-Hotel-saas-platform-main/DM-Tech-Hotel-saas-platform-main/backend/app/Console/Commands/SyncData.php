<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OfflineSyncService;

class SyncData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:data {outlet?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pushes pending offline sync logs to the cloud API.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $outletId = $this->argument('outlet');

        if ($outletId) {
            $this->info("Dispatching sync job for outlet {$outletId} to queue...");
            \App\Jobs\SyncToCloudJob::dispatch((int) $outletId);
        } else {
            $this->info('Identifying outlets with pending sync logs...');
            $outletIds = \App\Models\SyncLog::whereIn('status', ['pending', 'failed'])
                ->where('attempts', '<', 5)
                ->distinct()
                ->pluck('outlet_id')
                ->filter();

            if ($outletIds->isEmpty()) {
                $this->info('No pending sync logs found.');
                return self::SUCCESS;
            }

            foreach ($outletIds as $id) {
                \App\Jobs\SyncToCloudJob::dispatch((int) $id);
                $this->line(" - Dispatched sync stream for outlet {$id}");
            }
        }

        $this->info('All sync streams pushed to "low" queue.');
        return self::SUCCESS;
    }
}
