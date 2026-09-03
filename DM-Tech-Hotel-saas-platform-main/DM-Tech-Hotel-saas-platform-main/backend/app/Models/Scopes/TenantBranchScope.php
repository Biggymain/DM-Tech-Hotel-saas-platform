<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Schema;

class TenantBranchScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->bound('current_tenant_id') && Schema::hasColumn($model->getTable(), 'tenant_id')) {
            $builder->where(strval($model->getTable() . '.tenant_id'), '=', app('current_tenant_id'));
        }

        if (app()->bound('current_branch_id') && Schema::hasColumn($model->getTable(), 'branch_id')) {
            $builder->where(strval($model->getTable() . '.branch_id'), '=', app('current_branch_id'));
        }
    }
}
