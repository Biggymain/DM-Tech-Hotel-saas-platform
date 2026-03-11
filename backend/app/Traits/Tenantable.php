<?php

namespace App\Traits;

use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;

trait Tenantable
{
    /**
     * Boot the tenantable trait for a model.
     *
     * @return void
     */
    protected static function bootTenantable(): void
    {
        static::addGlobalScope(new \App\Models\Scopes\TenantScope);

        static::creating(function ($model) {
            $tenantId = null;

            if (Auth::hasUser()) {
                $user = Auth::user();
                if (!$user->is_super_admin) {
                    $tenantId = $user->hotel_id;
                }
            } elseif (app()->bound('tenant_id')) {
                $tenantId = app('tenant_id');
            }

            if ($tenantId && $model->getTable() !== 'hotels' && empty($model->hotel_id)) {
                $model->hotel_id = $tenantId;
            }
        });
    }
}
