<?php

use Illuminate\Database\Migrations\Migration;

class SeedGuestPortalPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissions = [
            ['name' => 'guest.requests.view', 'slug' => 'guest.requests.view'],
            ['name' => 'guest.requests.manage', 'slug' => 'guest.requests.manage'],
        ];

        foreach ($permissions as $perm) {
            \App\Models\Permission::firstOrCreate(['slug' => $perm['slug']], $perm);
        }

        // Assign to SuperAdmin, HotelOwner, Manager, Reception, Housekeeping
        $roles = \App\Models\Role::whereIn('name', ['SuperAdmin', 'HotelOwner', 'Manager', 'Reception', 'Housekeeping'])->get();
        $permissionIds = \App\Models\Permission::whereIn('slug', array_column($permissions, 'slug'))->pluck('id');

        foreach ($roles as $role) {
            $role->permissions()->syncWithoutDetaching($permissionIds);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $slugs = ['guest.requests.view', 'guest.requests.manage'];
        \App\Models\Permission::whereIn('slug', $slugs)->delete();
    }
}
