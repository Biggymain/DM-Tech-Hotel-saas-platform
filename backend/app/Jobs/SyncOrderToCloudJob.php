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
use App\Helpers\InternetConnection;
use Exception;

class SyncOrderToCloudJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $syncQueueId;

    public function __construct(int $syncQueueId)
    {
        $this->syncQueueId = $syncQueueId;
    }

    public function handle(): void
    {
        $syncRecord = null;
        
        // Ensure atomic sync locking blocking concurrent duplicate attempts gracefully 
        DB::transaction(function () use (&$syncRecord) {
            $syncRecord = SyncQueue::where('id', $this->syncQueueId)
                ->whereIn('status', ['pending', 'failed'])
                ->lockForUpdate()
                ->first();

            if ($syncRecord) {
                $syncRecord->update(['status' => 'processing']);
            }
        });

        if (!$syncRecord) {
            return; 
        }

        try {
            // Handle cloud deletions natively preserving identity constraints mapping appropriately
            if ($syncRecord->action === 'deleted') {
                DB::connection('mysql')->table('orders')
                    ->where('uuid', $syncRecord->model_uuid)
                    ->delete();
            } else {
                $orderData = [];
                if (isset($syncRecord->payload['data']) && is_array($syncRecord->payload['data'])) {
                    $orderData = $syncRecord->payload['data'];
                } elseif (isset($syncRecord->payload['data']) && is_object($syncRecord->payload['data'])) {
                    $orderData = json_decode(json_encode($syncRecord->payload['data']), true);
                }

                // Explicitly strip isolated auto-incrementing Edge-Node `id` keys preventing collision!
                unset($orderData['id']);

                // Guarantee Timestamp Consistency natively tracking Cloud injections seamlessly 
                $orderData['synced_at'] = now(); 
                
                // Idempotent sync executing updates dynamically based on UUID mappings accurately
                DB::connection('mysql')->table('orders')->updateOrInsert(
                    ['uuid' => $syncRecord->model_uuid],
                    $orderData
                );
            }

            // Document synchronized tracking status accurately mapped backwards
            $syncRecord->update([
                'status' => 'completed',
                'synced_at' => now(),
            ]);

            Order::withoutGlobalScopes()->where('uuid', $syncRecord->model_uuid)->update([
                'synced_at' => now(),
            ]);

        } catch (Exception $e) {
            $syncRecord->increment('attempts');
            $syncRecord->update([
                'status' => 'failed',
                'last_error' => $e->getMessage(),
            ]);
        }
    }
}
