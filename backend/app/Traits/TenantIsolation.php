<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait TenantIsolation
{
    /**
     * Boot the strict tenant isolation global scope for a model.
     */
    protected static function bootTenantIsolation(): void
    {
        static::addGlobalScope('tenant_isolation_strict', function (Builder $builder) {
            // Null-safe check: Do not execute isolation queries via unauthenticated terminal processes / API
            if (!Auth::check() || (app()->runningInConsole() && !Auth::hasUser())) {
                return; 
            }

            $user = Auth::user();
            if (!$user) return;

            $tableName = $builder->getModel()->getTable();

            // Super admin mapping
            if ($user->is_super_admin) {
                if (session()->has('active_hotel_id')) {
                    $builder->where($tableName . '.hotel_id', '=', session('active_hotel_id'));
                }
                return;
            }

            $hasHotelId = Schema::hasColumn($tableName, 'hotel_id');
            $hasBranchId = Schema::hasColumn($tableName, 'branch_id');
            $hasOutletId = Schema::hasColumn($tableName, 'outlet_id');

            // --- 1. Tenant Hotel Strict Scope ---
            if ($hasHotelId) {
                if (!empty($user->hotel_group_id) && empty($user->hotel_id)) {
                    // Group Admins bypass strict hotel boundaries but MUST belong to the Group
                    if (Schema::hasColumn($tableName, 'hotel_group_id')) {
                        $builder->where($tableName . '.hotel_group_id', '=', $user->hotel_group_id);
                    } else {
                        // Native mapping via branch linking
                        $branchIds = \App\Models\HotelGroup::withoutGlobalScopes()
                            ->find($user->hotel_group_id)?->branches()->withoutGlobalScopes()->pluck('hotels.id')->toArray() ?? [];
                        
                        if (!empty($branchIds)) {
                            $builder->whereIn($tableName . '.hotel_id', $branchIds);
                        } else {
                            $builder->whereRaw('1 = 0');
                        }
                    }
                } elseif (!empty($user->hotel_id)) {
                    $builder->where($tableName . '.hotel_id', '=', $user->hotel_id);
                } else {
                    $builder->whereRaw('1 = 0');
                }
            }

            // --- 2. Outlet / Branch Scope ---
            // Requirement explicitly states: "Do NOT fully apply branch-level scope to User model"
            if ($tableName === 'users') {
                return;
            }

            $branchCol = $hasBranchId ? 'branch_id' : ($hasOutletId ? 'outlet_id' : null);

            if ($branchCol && !empty($user->outlet_id)) {
                // Exceptional group roles bypass the localized restriction implicitly 
                if ($user->isGroupAdmin() || current(array_filter($user->roles->toArray(), fn($r) => in_array($r['slug'], ['general-manager', 'generalmanager'])))) {
                    return; 
                }

                $builder->where($tableName . '.' . $branchCol, '=', $user->outlet_id);
            }
        });

        // Auto-inject tenant dependencies upon record creation (migrated securely from legacy Tenantable)
        static::creating(function ($model) {
            if ($model instanceof \App\Models\Hotel) {
                return;
            }

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

            if ($tenantId && Schema::hasColumn($model->getTable(), 'hotel_id')) {
                $model->hotel_id = $tenantId;
            }

            if (empty($model->hotel_group_id) && Auth::hasUser()) {
                $user = Auth::user();
                if ($user->hotel_group_id && Schema::hasColumn($model->getTable(), 'hotel_group_id')) {
                    $model->hotel_group_id = $user->hotel_group_id;
                }
            }
            
            // Auto inject branch mapping implicitly
            $branchCol = Schema::hasColumn($model->getTable(), 'branch_id') ? 'branch_id' : (Schema::hasColumn($model->getTable(), 'outlet_id') ? 'outlet_id' : null);
            if ($branchCol && empty($model->{$branchCol}) && Auth::hasUser()) {
                $user = Auth::user();
                if ($user->outlet_id) {
                    $model->{$branchCol} = $user->outlet_id;
                }
            }
        });
    }
}
