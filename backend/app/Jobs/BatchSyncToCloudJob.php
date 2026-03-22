<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\SyncQueue;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class BatchSyncToCloudJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $records = collect();

        // Target up to 50 unprocessed entries blocking localized collisions
        DB::transaction(function () use (&$records) {
            // Cutoff infinite retries globally explicitly bounding resource drains
            SyncQueue::where('status', 'failed')
                ->where('attempts', '>=', 5)
                ->update(['status' => 'permanently_failed']);

            // Priority Ordering: prevent starvation naturally isolating records 
            $records = SyncQueue::whereIn('status', ['pending', 'failed'])
                ->orderByRaw("CASE WHEN status = 'pending' THEN 1 WHEN status = 'failed' THEN 2 END")
                ->lockForUpdate()
                ->limit(50)
                ->get();

            if ($records->isNotEmpty()) {
                SyncQueue::whereIn('id', $records->pluck('id'))->update(['status' => 'processing']);
            }
        });

        if ($records->isEmpty()) {
            return;
        }

        try {
            $payload = $records->map(function ($record) {
                return [
                    'id' => $record->id,
                    'uuid' => $record->uuid,
                    'model' => $record->model_type,
                    'model_uuid' => $record->model_uuid,
                    'action' => $record->action,
                    'data' => $record->payload['data'] ?? [],
                    'timestamp' => $record->payload['device_timestamp'] ?? now()->toIso8601String()
                ];
            })->toArray();

            // Payload Size Limit checking (Chunk automatically if payload > 1MB)
            $encodedPayload = json_encode($payload);
            $chunks = (strlen($encodedPayload) > 1000000) ? array_chunk($payload, 10) : [$payload];

            $cloudUrl = env('CLOUD_API_URL', 'https://api.omnistay.com') . '/api/v1/sync/batch';
            
            foreach ($chunks as $chunk) {
                // Implement fast failure & controlled retry internally skipping worker stack locks
                $response = Http::timeout(5)->retry(2, 100)->post($cloudUrl, [
                    'records' => $chunk
                ]);

                if ($response->successful()) {
                    $results = $response->json('results') ?? [];
                    
                    // Interpret partial success natively executing granular states mapping correctly 
                    foreach ($results as $result) {
                        $syncId = $result['id'] ?? null;
                        if (!$syncId) continue;
                        
                        if (($result['status'] ?? '') === 'ok') {
                            SyncQueue::where('id', $syncId)->update([
                                'status' => 'completed',
                                'synced_at' => now()
                            ]);

                            $recordMatch = $records->firstWhere('id', $syncId);
                            if ($recordMatch && $recordMatch->model_type === Order::class && $recordMatch->model_uuid) {
                                Order::withoutGlobalScopes()->where('uuid', $recordMatch->model_uuid)->update(['synced_at' => now()]);
                            }
                        } else {
                            SyncQueue::where('id', $syncId)->increment('attempts');
                            SyncQueue::where('id', $syncId)->update([
                                'status' => 'failed',
                                'last_error' => $result['error'] ?? 'API partial validation failure.'
                            ]);
                        }
                    }
                } else {
                    throw new Exception("Cloud API HTTP " . $response->status() . " rejection.");
                }
            }

        } catch (Exception $e) {
            // Structured Logging organically saving debugging traces globally seamlessly
            Log::error('Batch sync failed', [
                'batch_size' => $records->count(),
                'failed_ids' => $records->pluck('id')->toArray(),
                'error' => $e->getMessage()
            ]);

            SyncQueue::whereIn('id', $records->pluck('id'))->increment('attempts');
            SyncQueue::whereIn('id', $records->pluck('id'))->update([
                'status' => 'failed',
                'last_error' => substr($e->getMessage(), 0, 500)
            ]);
        }
    }
}
