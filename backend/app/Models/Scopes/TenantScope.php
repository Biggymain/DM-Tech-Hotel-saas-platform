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
     *
     * IMPORTANT: This scope is a no-op for:
     *  - Unauthenticated requests (login, register)
     *  - The 'hotels' table itself (Hotel IS the tenant; scoping by hotel_id makes no sense)
     *  - Super Admins without an active_hotel_id session (they see all data)
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Hotel is the root tenant model — never scope it.
        if ($model->getTable() === 'hotels') {
            return;
        }

        if (Auth::check()) {
            $user = Auth::user();

            // ── SUPER ADMIN ───────────────────────────────────────────────────────
            // Super admins see ALL data unless they have explicitly switched to a
            // specific branch via the session Branch Switcher.
            if ($user->is_super_admin) {
                if (session()->has('active_hotel_id')) {
                    $builder->where($model->getTable() . '.hotel_id', session('active_hotel_id'));
                }
                // else: no scope applied — full cross-tenant visibility
                return;
            }

            // ── GROUP ADMIN ───────────────────────────────────────────────────────
            // GROUP_ADMIN users have hotel_id = null but hotel_group_id set.
            // They must see ALL branches within their group but ZERO branches
            // from any other organization (data leakage boundary).
            if (!empty($user->hotel_group_id) && empty($user->hotel_id)) {
                $branchIds = \App\Models\HotelGroup::find($user->hotel_group_id)
                    ?->branches()
                    ->pluck('id')
                    ->toArray() ?? [];

                $builder->whereIn($model->getTable() . '.hotel_id', $branchIds);
                return;
            }

            // ── BRANCH USER (Manager, Receptionist, Staff) ────────────────────────
            // Strictly scoped to their assigned hotel_id only.
            if (!empty($user->hotel_id)) {
                $builder->where($model->getTable() . '.hotel_id', $user->hotel_id);
                return;
            }

            // User has neither hotel_id nor hotel_group_id — allow no data through
            $builder->whereRaw('1 = 0');
            return;
        }

        if (app()->bound('tenant_id')) {
            // Bound by middleware (e.g. Guest Portal QR sessions)
            $builder->where($model->getTable() . '.hotel_id', app('tenant_id'));
            return;
        }

        // ── NO AUTH, NO BOUND TENANT ──────────────────────────────────────────────
        // This covers login page, register page, and Artisan seeders.
        // Do NOT apply any scope so these operations succeed on a fresh database.
    }
}
