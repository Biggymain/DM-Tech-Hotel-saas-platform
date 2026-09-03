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
    public int $outletId;

    public function __construct(int $outletId) {
        $this->outletId = $outletId;
        $this->onQueue('low');
    }

    public function middleware(): array
    {
        return [new \Illuminate\Queue\Middleware\WithoutOverlapping($this->outletId)];
    }

    public function handle(OfflineSyncService $syncService): void
    {
        // 1. Internet Check
        if (!$this->hasInternet()) {
            $this->release(600); // Try again in 10 mins
            return;
        }

        // 2. Batch Sync specifically for this outlet
        $syncService->syncToCloud($this->outletId);
        
        Log::info("SyncToCloudJob: Processed sync for outlet {$this->outletId}.");
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
