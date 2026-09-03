<?php

namespace App\Traits;

use App\Models\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

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
            // Hotels are root tenants — never auto-inject hotel_id on them.
            // They are linked to a group via hotel_group_id instead.
            if ($model instanceof \App\Models\Hotel) {
                return;
            }

            // If hotel_id is already explicitly set, honour it
            if (!empty($model->hotel_id)) {
                return;
            }

            $tenantId = null;

            if (app()->bound('tenant_id')) {
                $tenantId = app('tenant_id');
            } elseif (Auth::hasUser()) {
                $user = Auth::user();
                if ($user->is_super_admin && session()->has('active_hotel_id')) {
                    $tenantId = session('active_hotel_id');
                } elseif (!$user->is_super_admin) {
                    $tenantId = $user->hotel_id;
                }
            }

            if ($tenantId) {
                $model->hotel_id = $tenantId;
            }

            // Also auto-assign hotel_group_id if not set and available
            if (empty($model->hotel_group_id) && Auth::hasUser()) {
                $user = Auth::user();
                if ($user->hotel_group_id && Schema::hasColumn($model->getTable(), 'hotel_group_id')) {
                    $model->hotel_group_id = $user->hotel_group_id;
                }
            }
        });
    }
}
