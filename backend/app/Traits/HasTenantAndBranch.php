<?php

namespace App\Traits;

use App\Models\Scopes\TenantBranchScope;
use Illuminate\Support\Facades\Schema;

trait HasTenantAndBranch
{
    /**
     * Boot the trait for a model.
     */
    protected static function bootHasTenantAndBranch(): void
    {
        static::addGlobalScope(new TenantBranchScope);

        static::creating(function ($model) {
            if (app()->bound('current_tenant_id') && empty($model->tenant_id) && Schema::hasColumn($model->getTable(), 'tenant_id')) {
                $model->tenant_id = app('current_tenant_id');
            }

            if (app()->bound('current_branch_id') && empty($model->branch_id) && Schema::hasColumn($model->getTable(), 'branch_id')) {
                $model->branch_id = app('current_branch_id');
            }
        });
    }
}
