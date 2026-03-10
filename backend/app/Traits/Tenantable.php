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
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (Auth::hasUser()) {
                $user = Auth::user();

                if (! $user->is_super_admin) {
                    if ($model->getTable() !== 'hotels' && empty($model->hotel_id)) {
                        $model->hotel_id = $user->hotel_id;
                    }
                }
            }
        });
    }
}
