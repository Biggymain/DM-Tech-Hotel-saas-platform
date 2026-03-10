<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'hotel.manage' => 'Hotel management',
            'rooms.manage' => 'Rooms management',
            'reservations.manage' => 'Reservations management',
            'restaurant.orders.manage' => 'Restaurant orders',
            'inventory.manage' => 'Inventory management',
            'finance.manage' => 'Finance management',
        ];

        // Seed permissions
        $dbPermissions = [];
        foreach ($permissions as $slug => $name) {
            $dbPermissions[$slug] = \App\Models\Permission::create([
                'name' => $name,
                'slug' => $slug,
                'module' => explode('.', $slug)[0],
            ]);
        }

        $roles = [
            'SuperAdmin' => [], // Has everything bypass
            'HotelOwner' => array_keys($permissions),
            'Manager' => ['rooms.manage', 'reservations.manage', 'restaurant.orders.manage', 'inventory.manage'],
            'Reception' => ['rooms.manage', 'reservations.manage'],
            'RestaurantStaff' => ['restaurant.orders.manage'],
            'KitchenStaff' => ['restaurant.orders.manage', 'inventory.manage'],
            'InventoryManager' => ['inventory.manage'],
            'FinanceOfficer' => ['finance.manage'],
        ];

        // Seed roles and attach permissions
        foreach ($roles as $roleName => $rolePermissions) {
            $role = \App\Models\Role::create([
                'name' => $roleName,
                'slug' => \Illuminate\Support\Str::slug($roleName),
                'is_system_role' => true,
            ]);

            $permissionIds = collect($rolePermissions)->map(function ($slug) use ($dbPermissions) {
                return $dbPermissions[$slug]->id;
            })->toArray();

            $role->permissions()->attach($permissionIds);
        }
    }
}
