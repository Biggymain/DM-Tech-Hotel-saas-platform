<?php

namespace App\Policies;

use App\Models\KitchenStation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class KitchenStationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Filtered in controller
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, KitchenStation $kitchenStation): bool
    {
        if ($user->is_super_admin) return true;
        return $user->hotel_id === $kitchenStation->hotel_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isBranchManager() || $user->isKitchenManager();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, KitchenStation $kitchenStation): bool
    {
        if ($user->is_super_admin) return true;
        return ($user->isBranchManager() || $user->isKitchenManager()) && $user->hotel_id === $kitchenStation->hotel_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, KitchenStation $kitchenStation): bool
    {
        if ($user->is_super_admin) return true;
        return $user->isBranchManager() && $user->hotel_id === $kitchenStation->hotel_id;
    }
}
