<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * RolesAndAdminSeeder
 *
 * Seeds the essential roles (GROUP_ADMIN, SUPER_ADMIN, hotel staff roles)
 * and creates a default Super Admin user for first-run setup.
 *
 * Run with: php artisan db:seed --class=RolesAndAdminSeeder
 */
class RolesAndAdminSeeder extends Seeder
{
    private const ROLES = [
        // Central SaaS roles
        ['name' => 'Super Admin',    'slug' => 'superadmin',       'level' => 100],
        ['name' => 'Group Admin',    'slug' => 'group-admin',       'level' => 90],
        // Hotel management
        ['name' => 'Hotel Owner',    'slug' => 'hotelowner',        'level' => 80],
        ['name' => 'General Manager','slug' => 'general-manager',   'level' => 70],
        ['name' => 'Receptionist',   'slug' => 'receptionist',      'level' => 50],
        // POS / Kitchen
        ['name' => 'Waiter',         'slug' => 'waiter',            'level' => 30],
        ['name' => 'Steward',        'slug' => 'steward',           'level' => 30],
        ['name' => 'Bartender',      'slug' => 'bartender',         'level' => 30],
        ['name' => 'Chef',           'slug' => 'chef',              'level' => 40],
        ['name' => 'Kitchen Manager','slug' => 'kitchen-manager',   'level' => 50],
        // Housekeeping
        ['name' => 'Housekeeper',    'slug' => 'housekeeping',      'level' => 30],
    ];

    public function run(): void
    {
        // ── 1. Seed roles (only columns that exist in our roles table) ──────────
        // Inspect the table to avoid guard_name / slug column mismatch errors
        $hasSlug       = Schema::hasColumn('roles', 'slug');
        $hasGuardName  = Schema::hasColumn('roles', 'guard_name');

        foreach (self::ROLES as $role) {
            $row = ['name' => $role['name'], 'updated_at' => now()];
            if ($hasSlug)      $row['slug']       = $role['slug'];
            if ($hasGuardName) $row['guard_name'] = 'sanctum';

            $lookupKey = $hasSlug ? ['slug' => $role['slug']] : ['name' => $role['name']];
            $createRow = array_merge($row, ['created_at' => now()]);

            DB::table('roles')->updateOrInsert($lookupKey, array_diff_key($createRow, ['created_at' => '']));
        }

        $superAdminRoleId = DB::table('roles')->where('slug', 'superadmin')->value('id');

        // ── 2. Create default Super Admin user if not present ─────────────────
        $existingAdmin = User::withoutGlobalScopes()->where('email', 'admin@dmtech.ng')->first();

        if (!$existingAdmin) {
            $admin = User::withoutGlobalScopes()->create([
                'name'          => 'DM Tech Admin',
                'email'         => 'admin@dmtech.ng',
                'password'      => Hash::make('Admin@123!'),
                'is_super_admin'=> true,
                'hotel_id'      => null,
                'hotel_group_id'=> null,
                'email_verified_at' => now(),
            ]);

            // Attach the Super Admin role via this project's user_roles pivot
            DB::table('user_roles')->insertOrIgnore([
                'role_id' => $superAdminRoleId,
                'user_id' => $admin->id,
            ]);

            $this->command->info("✅ Super Admin created: admin@dmtech.ng / Admin@123!");
        } else {
            $this->command->info('ℹ️  Super Admin already exists — skipping creation.');
        }

        $this->command->info('✅ Roles seeded: ' . implode(', ', array_column(self::ROLES, 'name')));
    }
}
