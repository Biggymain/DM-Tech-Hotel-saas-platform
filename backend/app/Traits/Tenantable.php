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
            // Hotels are root tenants — never auto-inject hotel_id on them
            if ($model->getTable() === 'hotels') {
                return;
            }

            // If hotel_id is already explicitly set, honour it
            if (!empty($model->hotel_id)) {
                return;
            }

            $tenantId = null;

            if (Auth::hasUser()) {
                $user = Auth::user();
                if ($user->is_super_admin && session()->has('active_hotel_id')) {
                    $tenantId = session('active_hotel_id');
                } elseif (!$user->is_super_admin) {
                    $tenantId = $user->hotel_id;
                }
            } elseif (app()->bound('tenant_id')) {
                $tenantId = app('tenant_id');
            }

            if ($tenantId) {
                $model->hotel_id = $tenantId;
            }
        });
    }
}
