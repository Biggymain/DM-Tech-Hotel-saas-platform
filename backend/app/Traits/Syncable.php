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
            \App\Jobs\SyncToCloudJob::dispatch()->afterCommit();
        });

        static::updated(function (Model $model) {
            static::recordSyncLog($model, 'update');
            \App\Jobs\SyncToCloudJob::dispatch()->afterCommit();
        });

        static::deleting(function (Model $model) {
            static::recordSyncLog($model, 'delete');
            \App\Jobs\SyncToCloudJob::dispatch()->afterCommit();
        });
    }

    protected static function recordSyncLog(Model $model, string $action)
    {
        $tenantId = $model->hotel_group_id ?? null;
        $branchId = $model->hotel_id ?? null;

        SyncLog::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
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
