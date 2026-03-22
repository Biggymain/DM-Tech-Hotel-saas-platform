<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class CloudSyncController extends Controller
{
    public function ingest(Request $request)
    {
        $tenantSecret = config('app.sync_tenant_secret');
        
        if (!$tenantSecret) {
            return response()->json(['error' => 'Server missing tenant secret configuration'], 500);
        }

        // Verify HMAC Signature
        $signature = $request->header('X-Sync-Signature');
        $jsonPayload = json_encode(['logs' => $request->input('logs')]);
        $expectedSignature = hash_hmac('sha256', $jsonPayload, $tenantSecret);

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('Cloud Sync Integrity check failed. Signatures do not match.');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $logs = $request->input('logs', []);
        
        $processed = 0;
        $ignored = 0;

        foreach ($logs as $logData) {
            DB::transaction(function () use ($logData, &$processed, &$ignored) {
                // Check if the UUID already exists in cloud sync_logs table (Idempotency)
                if (SyncLog::where('id', $logData['id'])->exists()) {
                    $ignored++;
                    return;
                }

                // Check version to see if incoming record is older than the currently applied version
                $existingLog = SyncLog::where('model_type', $logData['model_type'])
                                      ->where('model_id', $logData['model_id'])
                                      ->orderBy('version', 'desc')
                                      ->first();

                $isConflict = false;
                if ($existingLog && strtotime($logData['version']) < strtotime($existingLog->version)) {
                    $isConflict = true;
                }

                // Create the central log tracking for idempotency and audit
                SyncLog::create([
                    'id' => $logData['id'],
                    'tenant_id' => $logData['tenant_id'],
                    'branch_id' => $logData['branch_id'],
                    'model_type' => $logData['model_type'],
                    'model_id' => $logData['model_id'],
                    'action' => $logData['action'],
                    'payload' => $logData['payload'],
                    'version' => $logData['version'],
                    'user_id' => $logData['user_id'],
                    'device_id' => $logData['device_id'],
                    'status' => $isConflict ? 'conflict' : 'synced',
                    'synced_at' => now(),
                    'attempts' => 1,
                ]);

                if ($isConflict) {
                    $ignored++;
                    return; // Ignore applying the older state
                }

                // Apply changes to the central database, without triggering Cloud's own Syncable boot methods
                $modelClass = $logData['model_type'];
                if (class_exists($modelClass)) {
                    Model::withoutEvents(function () use ($modelClass, $logData) {
                        if ($logData['action'] === 'delete') {
                            $instance = $modelClass::find($logData['model_id']);
                            if ($instance) {
                                $instance->delete();
                            }
                        } else {
                            if ($logData['action'] === 'create') {
                                $instance = new $modelClass;
                                $instance->{$instance->getKeyName()} = $logData['model_id'];
                                // For Eloquent mass assignments or manual fill
                                foreach ($logData['payload'] as $key => $value) {
                                    $instance->{$key} = $value;
                                }
                                $instance->save();
                            } else if ($logData['action'] === 'update') {
                                $instance = $modelClass::find($logData['model_id']);
                                if ($instance) {
                                    foreach ($logData['payload'] as $key => $value) {
                                        $instance->{$key} = $value;
                                    }
                                    $instance->save();
                                } else {
                                    // If update arrives but model doesn't exist (out of order edge case), 
                                    // create it or ignore. Usually we recreate.
                                    $instance = new $modelClass;
                                    $instance->{$instance->getKeyName()} = $logData['model_id'];
                                    foreach ($logData['payload'] as $key => $value) {
                                        $instance->{$key} = $value;
                                    }
                                    $instance->save();
                                }
                            }
                        }
                    });
                }
                
                $processed++;
            });
        }

        return response()->json([
            'status' => 'success',
            'processed' => $processed,
            'ignored' => $ignored,
        ]);
    }
}
