<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PermissionService
{
    /**
     * Check if a user has a specific permission based on their assigned roles.
     *
     * @param User $user
     * @param string $permissionSlug
     * @return bool
     */
    public function hasPermission(User $user, string $permissionSlug): bool
    {
        // 1. Super Admin Bypass
        if ($user->is_super_admin) {
            return true;
        }

        // 2. Load roles with their permissions
        // We can cache this query per user to optimize performance per request cycle.
        $cacheKey = "user_permissions_{$user->id}";
        
        $userPermissions = Cache::remember($cacheKey, 60 * 60, function () use ($user) {
            // Get all permission slugs associated with the user's roles
            return $user->roles()
                ->with('permissions')
                ->get()
                ->flatMap(function ($role) {
                    return $role->permissions->pluck('slug');
                })
                ->unique()
                ->toArray();
        });

        // 3. Check if the required permission is in the user's permissions array
        \Illuminate\Support\Facades\Log::info('Checking permission', [
            'user_id' => $user->id,
            'required' => $permissionSlug,
            'has_permissions' => $userPermissions,
        ]);
        
        return in_array($permissionSlug, $userPermissions);
    }
    
    /**
     * Clear the cached permissions for a user.
     * Use this when a user's roles or permissions are updated.
     * 
     * @param User $user
     * @return void
     */
    public function clearPermissionCache(User $user): void
    {
        Cache::forget("user_permissions_{$user->id}");
    }
}
