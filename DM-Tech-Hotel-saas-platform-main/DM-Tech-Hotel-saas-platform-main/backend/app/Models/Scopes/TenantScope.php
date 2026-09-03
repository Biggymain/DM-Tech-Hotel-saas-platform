<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class TenantScope implements Scope
{
    private static bool $isApplying = false;
    private static ?int $resolvedTenantId = null;
    private static ?array $resolvedBranchIds = null;

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * IMPORTANT: This scope is a no-op for:
     *  - Unauthenticated requests (login, register)
     *  - The 'hotels' table itself (Hotel IS the tenant; scoping by hotel_id makes no sense)
     *  - Super Admins without an active_hotel_id session (they see all data)
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (self::$isApplying) {
            return;
        }

        // Hard reset if app context changes (Crucial for PHPUnit)
        if (app()->bound('tenant_id')) {
            self::$resolvedTenantId = (int)app('tenant_id');
            self::$resolvedBranchIds = null;
        }

        self::$isApplying = true;

        try {
            $this->executeApply($builder, $model);
        } finally {
            self::$isApplying = false;
        }
    }

    private function executeApply(Builder $builder, Model $model): void
    {
        $tenantId = $this->getTenantId();
        $branchId = app()->bound('current_branch_id') ? (int)app('current_branch_id') : null;

        // 1. Hotels Table Isolation
        if ($model instanceof \App\Models\Hotel || $model->getTable() === 'hotels') {
            if ($tenantId) {
                $builder->where($model->qualifyColumn('id'), $tenantId);
            }
            return;
        }

        // 2. Primary Scope: Tenant (hotel_id)
        if ($tenantId) {
            $builder->where($model->qualifyColumn('hotel_id'), $tenantId);

            // 3. Sub-Scope: Branch (If model supports it and branch is bound)
            // We check for both 'outlet_id' and 'branch_id' as they are used interchangeably in legacy code
            if ($branchId) {
                if (Schema::hasColumn($model->getTable(), 'outlet_id')) {
                    $builder->where($model->qualifyColumn('outlet_id'), $branchId);
                } elseif (Schema::hasColumn($model->getTable(), 'branch_id')) {
                    $builder->where($model->qualifyColumn('branch_id'), $branchId);
                }
            }
            return;
        }

        // 4. Fallback for unauthenticated access / Artisan commands
        // If we are in the console or have a bound tenant, we've already handled it.
        // Group Admin multi-branch scoping is now bound in TenantIsolationMiddleware exclusively.
    }

    /**
     * Resilient tenant ID resolution for early lifecycle binding.
     */
    private function getTenantId(): ?int
    {
        if (self::$resolvedTenantId !== null) {
            return self::$resolvedTenantId;
        }

        // 1. Primary Source: App Container (Bound by Middleware)
        $id = app()->bound('tenant_id') ? app('tenant_id') : null;

        // 2. Secondary Source: Active Context (In-Memory/Global)
        if (!$id) {
            $id = app()->bound('active_hotel_id') ? app('active_hotel_id') : null;
        }

        // 3. Late Fallback: Check Request Context
        if (!$id && !app()->runningInConsole()) {
             $id = request()->header('X-Tenant-ID') 
                ?? request()->header('X-Hotel-Context')
                ?? session('active_hotel_id');
        }

        self::$resolvedTenantId = $id ? (int)$id : null;
        return self::$resolvedTenantId;
    }
}
