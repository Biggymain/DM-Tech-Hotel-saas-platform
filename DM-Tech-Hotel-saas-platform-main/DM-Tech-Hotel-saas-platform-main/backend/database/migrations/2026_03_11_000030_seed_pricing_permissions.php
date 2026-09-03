<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissions = [
            'pricing.rate_plans.view',
            'pricing.rate_plans.manage',
            'pricing.seasonal_rates.manage',
            'pricing.occupancy_rules.manage'
        ];

        foreach ($permissions as $permission) {
            \App\Models\Permission::firstOrCreate(['name' => $permission, 'slug' => $permission]);
        }

        $roles = \App\Models\Role::whereIn('name', ['Manager', 'HotelOwner'])->get();
        $permissionsList = \App\Models\Permission::whereIn('name', $permissions)->get();

        foreach ($roles as $role) {
            foreach ($permissionsList as $permission) {
                // Check if the permission is already attached
                if (!$role->permissions()->where('permissions.id', $permission->id)->exists()) {
                    $role->permissions()->attach($permission->id);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissions = [
            'pricing.rate_plans.view',
            'pricing.rate_plans.manage',
            'pricing.seasonal_rates.manage',
            'pricing.occupancy_rules.manage'
        ];

        $roles = \App\Models\Role::whereIn('name', ['Manager', 'HotelOwner'])->get();
        $permissionsList = \App\Models\Permission::whereIn('name', $permissions)->get();

        foreach ($roles as $role) {
            $role->permissions()->detach($permissionsList->pluck('id'));
        }

        \App\Models\Permission::whereIn('name', $permissions)->delete();
    }
};
