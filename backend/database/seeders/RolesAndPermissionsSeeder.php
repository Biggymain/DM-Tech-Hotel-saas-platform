<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'SuperAdmin', 'slug' => 'superadmin'],
            ['name' => 'HotelOwner', 'slug' => 'hotelowner'],
            ['name' => 'Manager', 'slug' => 'manager'],
            ['name' => 'Reception', 'slug' => 'reception'],
            ['name' => 'RestaurantStaff', 'slug' => 'restaurantstaff'],
            ['name' => 'KitchenStaff', 'slug' => 'kitchenstaff'],
            ['name' => 'InventoryManager', 'slug' => 'inventorymanager'],
            ['name' => 'FinanceOfficer', 'slug' => 'financeofficer'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
