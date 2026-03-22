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
    protected $signature = 'sync:data';

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
        $this->info('Dispatching sync job to queue...');
        \App\Jobs\SyncToCloudJob::dispatch();
        $this->info('Sync job pushed to "low" queue.');
        return Command::SUCCESS;
    }
}
