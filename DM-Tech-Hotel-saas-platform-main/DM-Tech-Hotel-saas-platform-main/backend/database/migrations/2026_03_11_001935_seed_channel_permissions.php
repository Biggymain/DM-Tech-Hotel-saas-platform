<?php

use Illuminate\Database\Migrations\Migration;

class SeedChannelPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $permissions = [
            ['name' => 'channels.integrations.view', 'slug' => 'channels.integrations.view'],
            ['name' => 'channels.integrations.manage', 'slug' => 'channels.integrations.manage'],
            ['name' => 'channels.sync.execute', 'slug' => 'channels.sync.execute'],
        ];

        foreach ($permissions as $perm) {
            \App\Models\Permission::firstOrCreate(['slug' => $perm['slug']], $perm);
        }

        // Attach to SuperAdmin, HotelOwner, Manager
        $roles = \App\Models\Role::whereIn('name', ['SuperAdmin', 'HotelOwner', 'Manager'])->get();
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
        $slugs = ['channels.integrations.view', 'channels.integrations.manage', 'channels.sync.execute'];
        \App\Models\Permission::whereIn('slug', $slugs)->delete();
    }
}
