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
        if (Auth::hasUser()) {
            $user = Auth::user();
            
            // SuperAdmins bypass the isolation scope
            if (! $user->is_super_admin) {
                $column = $model->getTable() === 'hotels' ? 'id' : 'hotel_id';
                $builder->where($model->getTable() . '.' . $column, $user->hotel_id);
            }
        }
    }
}
