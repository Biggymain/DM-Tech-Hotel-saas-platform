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

    public $queue = 'low';
    public $tries = 5;
    public $backoff = [300, 600, 1800]; // More aggressive backoff for sync

    public function handle(OfflineSyncService $syncService): void
    {
        // 1. Internet Check
        if (!$this->hasInternet()) {
            $this->release(600); // Try again in 10 mins
            return;
        }

        // 2. Batch Sync (50 records as per Module 4)
        $syncedCount = $syncService->synchronize(50);
        
        Log::info("SyncToCloudJob: Processed {$syncedCount} records.");
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
