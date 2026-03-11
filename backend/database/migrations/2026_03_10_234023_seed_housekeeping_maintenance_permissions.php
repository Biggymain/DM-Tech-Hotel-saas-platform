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
            ['name' => 'View Housekeeping Tasks', 'slug' => 'housekeeping.tasks.view', 'module' => 'housekeeping'],
            ['name' => 'Manage Housekeeping Tasks', 'slug' => 'housekeeping.tasks.manage', 'module' => 'housekeeping'],
            ['name' => 'View Maintenance Requests', 'slug' => 'maintenance.requests.view', 'module' => 'maintenance'],
            ['name' => 'Manage Maintenance Requests', 'slug' => 'maintenance.requests.manage', 'module' => 'maintenance'],
        ];

        foreach ($permissions as $permission) {
            \App\Models\Permission::firstOrCreate(['slug' => $permission['slug']], $permission);
        }

        // Attach to roles
        $manager = \App\Models\Role::where('name', 'Manager')->first();
        if ($manager) {
            $manager->permissions()->syncWithoutDetaching(
                \App\Models\Permission::whereIn('slug', collect($permissions)->pluck('slug'))->pluck('id')
            );
        }
        
        $housekeeping = \App\Models\Role::where('name', 'Housekeeping')->first();
        if ($housekeeping) {
            $housekeeping->permissions()->syncWithoutDetaching(
                \App\Models\Permission::whereIn('slug', ['housekeeping.tasks.view', 'housekeeping.tasks.manage'])->pluck('id')
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \App\Models\Permission::whereIn('slug', [
            'housekeeping.tasks.view',
            'housekeeping.tasks.manage',
            'maintenance.requests.view',
            'maintenance.requests.manage'
        ])->delete();
    }
};
