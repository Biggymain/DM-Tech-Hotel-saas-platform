<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Order;
use App\Models\SyncQueue;
use Exception;

class SyncController extends Controller
{
    /**
     * Ingestion Endpoint specifically tracking Master Cloud DB records structurally guaranteeing idempotency naturally.
     */
    public function batchSync(Request $request)
    {
        $records = $request->input('records', []);

        if (empty($records)) {
            return response()->json(['message' => 'No sync payload provided'], 400);
        }

        $results = [];

        foreach ($records as $record) {
            DB::beginTransaction();
            try {
                if ($record['model'] === Order::class) {
                    if ($record['action'] === 'deleted') {
                        DB::table('orders')->where('uuid', $record['model_uuid'])->delete();
                    } else {
                        $orderData = $record['data'] ?? [];
                        
                        // Parse JSON payload structures cleanly mapping nested data optimally
                        if (is_string($orderData)) {
                            $orderData = json_decode($orderData, true);
                        } elseif (is_object($orderData)) {
                            $orderData = json_decode(json_encode($orderData), true);
                        }
                        
                        // Guarantee absolute collision immunity
                        unset($orderData['id']);
                        $orderData['synced_at'] = now();

                        DB::table('orders')->updateOrInsert(
                            ['uuid' => $record['model_uuid']],
                            $orderData
                        );
                    }
                }

                DB::commit();
                $results[] = [
                    'id' => $record['id'] ?? null,
                    'uuid' => $record['uuid'] ?? null,
                    'status' => 'ok'
                ];
            } catch (Exception $e) {
                DB::rollBack();
                $results[] = [
                    'id' => $record['id'] ?? null,
                    'uuid' => $record['uuid'] ?? null,
                    'status' => 'failed',
                    'error' => substr($e->getMessage(), 0, 255) // Bound sizes safely
                ];
            }
        }

        return response()->json([
            'message' => 'Batch processing completed with execution tracking maps natively.',
            'results' => $results
        ], 200);
    }

    /**
     * Exposes absolute dashboard stats capturing local edge nodes' sync stability natively.
     */
    public function syncStatus()
    {
        return response()->json([
            'pending' => SyncQueue::where('status', 'pending')->count(),
            'processing' => SyncQueue::where('status', 'processing')->count(),
            'failed' => SyncQueue::where('status', 'failed')->count(),
            'permanently_failed' => SyncQueue::where('status', 'permanently_failed')->count(),
            'synced_today' => SyncQueue::where('status', 'completed')
                ->whereDate('synced_at', now()->toDateString())
                ->count(),
        ], 200);
    }
}
