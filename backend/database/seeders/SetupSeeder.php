<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Hotel;
use App\Models\Role;
use App\Models\Outlet;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

/**
 * SetupSeeder
 *
 * Creates the first-run data required to boot the platform:
 *  - Hotel Group Root: DM Tech (represents the SaaS company/hotel group)
 *  - Branch: Royal Spring Hotel (first tenant hotel)
 *  - Branch: Royal Spring - Lagos Branch (second hotel/branch example)
 *  - Super Admin user linked to the root hotel
 *
 * Run with: php artisan db:seed --class=SetupSeeder
 */
class SetupSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🏨  Starting first-run platform setup...');

        // Step 1: Seed roles and permissions first
        $this->call(RoleAndPermissionSeeder::class);

        DB::transaction(function () {
            // Step 2: Create main hotel (Royal Spring Hotel)
            $mainHotel = Hotel::firstOrCreate(
                ['domain' => 'royalspring.com'],
                [
                    'name'       => 'Royal Spring Hotel',
                    'email'      => 'info@royalspring.com',
                    'phone'      => '+2348000000000',
                    'address'    => '14 Victoria Island, Lagos, Nigeria',
                    'is_active'  => true,
                ]
            );

            \App\Models\HotelSetting::create([
                'hotel_id' => $mainHotel->id,
                'setting_key' => 'loyalty_conversion_rate',
                'setting_value' => '5000',
                'type' => 'integer'
            ]);

            // Step 3: Create a second branch/hotel
            $branch = Hotel::firstOrCreate(
                ['domain' => 'abuja.royalspring.com'],
                [
                    'name'      => 'Royal Spring Hotel - Abuja Branch',
                    'email'     => 'abuja@royalspring.com',
                    'phone'     => '+2348000000001',
                    'address'   => '5 Central Business District, Abuja, Nigeria',
                    'is_active' => true,
                ]
            );

            \App\Models\HotelSetting::create([
                'hotel_id' => $branch->id,
                'setting_key' => 'loyalty_conversion_rate',
                'setting_value' => '5000',
                'type' => 'integer'
            ]);

            // Step 4: Create Super Admin (not scoped to one hotel — is_super_admin grants cross-hotel access)
            $superAdmin = User::firstOrCreate(
                ['email' => 'superadmin@dmtech.com'],
                [
                    'name'           => 'DM Tech Super Admin',
                    'password'       => Hash::make('SuperAdmin@2026!'),
                    'is_super_admin' => true,
                    'hotel_id'       => $mainHotel->id,
                ]
            );

            // Step 5: Create a Hotel Owner for Royal Spring Hotel (for testing non-super-admin login)
            $hotelOwner = User::firstOrCreate(
                ['email' => 'admin@royalspring.com'],
                [
                    'name'           => 'Royal Spring Admin',
                    'password'       => Hash::make('HotelAdmin@2026!'),
                    'is_super_admin' => false,
                    'hotel_id'       => $mainHotel->id,
                ]
            );

            // Step 6: Attach HotelOwner role
            $ownerRole = Role::where('slug', 'hotelowner')->first();
            $superRole = Role::where('slug', 'superadmin')->first();

            if ($ownerRole) {
                $hotelOwner->roles()->syncWithoutDetaching([
                    $ownerRole->id => ['hotel_id' => $mainHotel->id]
                ]);
            }

            if ($superRole) {
                $superAdmin->roles()->syncWithoutDetaching([
                    $superRole->id => ['hotel_id' => $mainHotel->id]
                ]);
            }

            // Step 7: Create default outlets for the main hotel
            $defaultOutlets = [
                ['name' => 'Main Restaurant', 'type' => 'restaurant'],
                ['name' => 'Room Service',    'type' => 'room_service'],
                ['name' => 'Main Bar',        'type' => 'bar'],
            ];

            foreach ($defaultOutlets as $outletData) {
                Outlet::firstOrCreate(
                    ['hotel_id' => $mainHotel->id, 'name' => $outletData['name']],
                    [
                        'hotel_id'  => $mainHotel->id,
                        'type'      => $outletData['type'],
                        'is_active' => true,
                    ]
                );
            }
        });

        $this->command->info('✅  Setup complete!');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Super Admin', 'superadmin@dmtech.com', 'SuperAdmin@2026!'],
                ['Hotel Admin', 'admin@royalspring.com', 'HotelAdmin@2026!'],
            ]
        );
    }
}
