<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = null;

        if (Auth::check()) {
            $user = Auth::user();
            if ($user->is_super_admin) {
                return;
            }
            $tenantId = $user->hotel_id;
        } elseif (app()->bound('tenant_id')) {
            $tenantId = app('tenant_id');
        }

        if ($tenantId) {
            $column = $model->getTable() === 'hotels' ? 'id' : 'hotel_id';
            $builder->where($model->getTable() . '.' . $column, $tenantId);
        }
    }
}
