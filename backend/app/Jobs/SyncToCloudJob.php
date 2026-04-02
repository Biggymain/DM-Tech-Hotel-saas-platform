<?php

namespace App\Jobs;

use App\Services\OfflineSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncToCloudJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 5;
    public $backoff = [300, 600, 1800]; // More aggressive backoff for sync

    public function __construct() {
        $this->onQueue('low');
    }

    public function handle(OfflineSyncService $syncService): void
    {
        // 1. Internet Check
        if (!$this->hasInternet()) {
            $this->release(600); // Try again in 10 mins
            return;
        }

        // 2. Batch Sync
        $syncService->syncToCloud();
        
        Log::info("SyncToCloudJob: Processed batch sync logic.");
    }

    private function hasInternet(): bool
    {
        try {
            $response = Http::timeout(5)->get('https://google.com');
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
}
