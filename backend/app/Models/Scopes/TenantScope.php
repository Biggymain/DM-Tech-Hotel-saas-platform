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

        self::$isApplying = true;

        try {
            $this->executeApply($builder, $model);
        } finally {
            self::$isApplying = false;
        }
    }

    private function executeApply(Builder $builder, Model $model): void
    {
        if (Auth::check()) {
            $user = Auth::user();

            // ── 0. Hotels Table Isolation ─────────────────────────────────────────
            // Even if the model IS a Hotel, we must scope it so Group Admins see
            // only their group's branches and Branch staff see only their hotel.
            if ($model->getTable() === 'hotels') {
                if ($user->is_super_admin) {
                     return; // Super Admin sees all hotels
                }
                
                if (!empty($user->hotel_group_id) && empty($user->hotel_id)) {
                    // Group Admin: can only see branches belonging to their group
                    $builder->where(function($q) use ($model, $user) {
                        $q->where($model->qualifyColumn('hotel_group_id'), $user->hotel_group_id);
                    });
                    return;
                }

                if (!empty($user->hotel_id)) {
                    // Branch User: can only see their own hotel branch
                    $builder->where(function($q) use ($model, $user) {
                        $q->where($model->qualifyColumn('id'), $user->hotel_id);
                    });
                    return;
                }
                
                $builder->whereRaw('1 = 0');
                return;
            }

            // ── SUPER ADMIN ───────────────────────────────────────────────────────
            // Super admins see ALL data unless they have explicitly switched to a
            // specific branch via the session Branch Switcher.
            if ($user->is_super_admin) {
                if (session()->has('active_hotel_id')) {
                    $builder->where(function($q) use ($model) {
                        $q->where($model->getTable() . '.hotel_id', session('active_hotel_id'));
                    });
                }
                // else: no scope applied — full cross-tenant visibility
                return;
            }

            // ── GROUP ADMIN ───────────────────────────────────────────────────────
            // GROUP_ADMIN users have hotel_id = null but hotel_group_id set.
            // They must see ALL branches within their group but ZERO branches
            // from any other organization (data leakage boundary).
            if (!empty($user->hotel_group_id) && empty($user->hotel_id)) {
                $branchIds = \App\Models\HotelGroup::withoutGlobalScopes()
                    ->find($user->hotel_group_id)
                    ?->branches()
                    ->withoutGlobalScopes()
                    ->pluck('hotels.id')
                    ->toArray() ?? [];

                $builder->where(function($q) use ($model, $user, $branchIds) {
                    // 1. Allow if explicitly linked to their group
                    if (Schema::hasColumn($model->getTable(), 'hotel_group_id')) {
                        $q->orWhere($model->qualifyColumn('hotel_group_id'), $user->hotel_group_id);
                    }

                    // 2. Allow if linked to one of their group's branches
                    $q->orWhereIn($model->qualifyColumn('hotel_id'), $branchIds);

                    // 3. Allow system-wide records (NULL hotel_id & NULL hotel_group_id)
                    // e.g. System Roles, Currencies, Global Settings
                    $q->orWhere(function($sub) use ($model) {
                        $sub->whereNull($model->qualifyColumn('hotel_id'));
                        if (Schema::hasColumn($model->getTable(), 'hotel_group_id')) {
                            $sub->whereNull($model->qualifyColumn('hotel_group_id'));
                        }
                    });
                });
                return;
            }

            // ── BRANCH USER (Manager, Receptionist, Staff) ────────────────────────
            // Strictly scoped to their assigned hotel_id only.
            if (!empty($user->hotel_id)) {
                $builder->where(function($q) use ($model, $user) {
                    $q->where($model->getTable() . '.hotel_id', $user->hotel_id)
                      ->orWhereNull($model->getTable() . '.hotel_id');
                });
                return;
            }

            // User has neither hotel_id nor hotel_group_id — allow no data through
            $builder->whereRaw('1 = 0');
            return;
        }

        if (app()->bound('tenant_id')) {
            // Bound by middleware (e.g. Guest Portal QR sessions)
            $builder->where(function($q) use ($model) {
                $q->where($model->getTable() . '.hotel_id', app('tenant_id'));
            });
            return;
        }

        // ── NO AUTH, NO BOUND TENANT ──────────────────────────────────────────────
        // This covers login page, register page, and Artisan seeders.
        // Do NOT apply any scope so these operations succeed on a fresh database.
    }
}
