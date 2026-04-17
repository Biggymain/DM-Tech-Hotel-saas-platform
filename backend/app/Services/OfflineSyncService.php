<?php

namespace App\Services;

use App\Models\SyncLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OfflineSyncService
{
    public function syncToCloud(int $outletId)
    {
        $cloudUrl = config('app.cloud_sync_url', 'https://api.cloud.dm-tech.com/api/v1/sync/ingest');
        $tenantSecret = config('app.sync_tenant_secret');
        $apiToken = config('app.sync_api_token');

        if (!$tenantSecret) {
            Log::warning('Tenant Secret not configured for sync.');
            return;
        }

        // Fetch pending logs specifically for this outlet in chunks of 50
        SyncLog::where('outlet_id', $outletId)
            ->whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', 5)
            ->chunkById(50, function ($logs) use ($cloudUrl, $tenantSecret, $apiToken) {
                
                $payloadArray = $logs->toArray();
                $jsonPayload = json_encode(['logs' => $payloadArray]);
                
                $signature = hash_hmac('sha256', $jsonPayload, $tenantSecret);

                $request = Http::withHeaders([
                    'X-Sync-Signature' => $signature,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]);

                if ($apiToken) {
                    $request->withToken($apiToken);
                }

                try {
                    $response = $request->post($cloudUrl, ['logs' => $payloadArray]);

                    if ($response->successful()) {
                        $logIds = $logs->pluck('id')->toArray();
                        SyncLog::whereIn('id', $logIds)->update([
                            'status' => 'synced',
                            'synced_at' => now(),
                        ]);
                    } else {
                        $this->markFailed($logs, $response->body());
                    }
                } catch (\Exception $e) {
                    $this->markFailed($logs, $e->getMessage());
                }
            });
    }

    protected function markFailed($logs, $error)
    {
        foreach ($logs as $log) {
            $log->increment('attempts');
            $log->update([
                'status' => 'failed',
                'last_error' => substr($error, 0, 500)
            ]);
        }
    }
}
