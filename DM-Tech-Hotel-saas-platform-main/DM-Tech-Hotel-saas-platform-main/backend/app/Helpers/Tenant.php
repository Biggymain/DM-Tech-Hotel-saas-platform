<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class Tenant
{
    /**
     * Reusable macro helper automatically resolving DB facade scopes manually
     * where Eloquent models cannot be evaluated.
     */
    public static function scopeQuery($query, string $tableName)
    {
        if (!Auth::check() || (app()->runningInConsole() && !Auth::hasUser())) {
            return $query;
        }

        $user = Auth::user();
        if ($user->is_super_admin) return $query;

        if ($user->hotel_id) {
            $query->where($tableName . '.hotel_id', $user->hotel_id);
        }

        $branchCol = Schema::hasColumn($tableName, 'branch_id') ? 'branch_id' : (Schema::hasColumn($tableName, 'outlet_id') ? 'outlet_id' : null);

        if ($branchCol && $user->outlet_id && $tableName !== 'users') {
            if (!$user->isGroupAdmin() && !current(array_filter($user->roles->toArray(), fn($r) => in_array($r['slug'], ['general-manager', 'generalmanager'])))) {
                $query->where($tableName . '.' . $branchCol, $user->outlet_id);
            }
        }

        return $query;
    }
}
