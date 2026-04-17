<?php

namespace App\Traits;

use App\Models\SyncLog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

trait Syncable
{
    public static function bootSyncable()
    {
        static::created(function (Model $model) {
            static::recordSyncLog($model, 'create');
            $outletId = $model->outlet_id ?? $model->hotel_id;
            if ($outletId) {
                \App\Jobs\SyncToCloudJob::dispatch((int) $outletId)->afterCommit();
            }
        });

        static::updated(function (Model $model) {
            static::recordSyncLog($model, 'update');
            $outletId = $model->outlet_id ?? $model->hotel_id;
            if ($outletId) {
                \App\Jobs\SyncToCloudJob::dispatch((int) $outletId)->afterCommit();
            }
        });

        static::deleting(function (Model $model) {
            static::recordSyncLog($model, 'delete');
            $outletId = $model->outlet_id ?? $model->hotel_id;
            if ($outletId) {
                \App\Jobs\SyncToCloudJob::dispatch((int) $outletId)->afterCommit();
            }
        });
    }

    protected static function recordSyncLog(Model $model, string $action)
    {
        $tenantId = $model->hotel_group_id ?? null;
        $branchId = $model->hotel_id ?? null;
        $outletId = $model->outlet_id ?? $branchId;

        SyncLog::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'outlet_id' => $outletId,
            'model_type' => get_class($model),
            'model_id' => (string) $model->getKey(),
            'action' => $action,
            'payload' => $model->toArray(),
            'version' => now(),
            'user_id' => auth()->check() ? auth()->id() : null,
            'device_id' => request()->header('X-Device-Id'),
            'status' => 'pending',
            'attempts' => 0,
        ]);
    }
}
