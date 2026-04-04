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

        if (Auth::check()) {
            $user = Auth::user();
            
            if (!$user) {
                return;
            }

            // ── 0. Hotels Table Isolation ─────────────────────────────────────────
            if ($model instanceof \App\Models\Hotel || $model->getTable() === 'hotels') {
                if ($user->is_super_admin) {
                     return; 
                }
                
                $idToMatch = $tenantId ?? $user->hotel_id;

                if ($idToMatch) {
                    $builder->where($model->qualifyColumn('id'), $idToMatch);
                    return;
                }
                
                if (!empty($user->hotel_group_id)) {
                    $builder->where($model->qualifyColumn('hotel_group_id'), $user->hotel_group_id);
                    return;
                }

                $builder->whereRaw('1 = 0');
                return;
            }

            // ── SUPER ADMIN ───────────────────────────────────────────────────────
            if ($user->is_super_admin) {
                if ($tenantId) {
                    $builder->where($model->qualifyColumn('hotel_id'), $tenantId);
                }
                return;
            }

            // ── GROUP ADMIN ───────────────────────────────────────────────────────
            if (!empty($user->hotel_group_id) && empty($user->hotel_id)) {
                if (self::$resolvedBranchIds === null) {
                    self::$resolvedBranchIds = \App\Models\HotelGroup::withoutGlobalScopes()
                        ->find($user->hotel_group_id)
                        ?->branches()
                        ->withoutGlobalScopes()
                        ->pluck('hotels.id')
                        ->toArray() ?? [];
                }
                $branchIds = self::$resolvedBranchIds;

                $builder->where(function($q) use ($model, $user, $branchIds) {
                    if (Schema::hasColumn($model->getTable(), 'hotel_group_id')) {
                        $q->orWhere($model->qualifyColumn('hotel_group_id'), $user->hotel_group_id);
                    }
                    $q->orWhereIn($model->qualifyColumn('hotel_id'), $branchIds);
                    $q->orWhere(function($sub) use ($model) {
                        $sub->whereNull($model->qualifyColumn('hotel_id'));
                        if (Schema::hasColumn($model->getTable(), 'hotel_group_id')) {
                            $sub->whereNull($model->qualifyColumn('hotel_id'));
                        }
                    });
                });
                return;
            }

            // ── BRANCH USER (Manager, Receptionist, Staff) ────────────────────────
            $idToFilter = $tenantId ?? $user->hotel_id;
            if ($idToFilter) {
                $builder->where(function($q) use ($model, $idToFilter) {
                    $q->where($model->qualifyColumn('hotel_id'), $idToFilter)
                      ->orWhereNull($model->qualifyColumn('hotel_id'));
                });
                return;
            }

            $builder->whereRaw('1 = 0');
            return;
        }

        if ($tenantId) {
            if ($model instanceof \App\Models\Hotel || $model->getTable() === 'hotels') {
                $builder->where($model->qualifyColumn('id'), $tenantId);
            } else {
                $builder->where($model->qualifyColumn('hotel_id'), $tenantId);
            }
            return;
        }

        // ── NO AUTH, NO BOUND TENANT ──────────────────────────────────────────────
        // This covers login page, register page, and Artisan seeders.
        // Do NOT apply any scope so these operations succeed on a fresh database.
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
