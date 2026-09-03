<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Hotel;
use App\Models\HotelGroup;
use App\Models\HotelGroupWebsite;
use App\Models\Role;
use App\Models\Outlet;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\HotelSetting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * EnterpriseHierarchySeeder
 *
 * Seeds the full Enterprise Multi-Branch hierarchy:
 *  - Parent Group: Royal Spring Group (Group ID: 1)
 *  - Branch A: Ikeja Main Hotel (Tenant ID: 1, Slug: ikeja)
 *  - Branch B: Victoria Island Hotel (Tenant ID: 2, Slug: vi)
 *  - Master Super Admin, Group Admin, GM, Receptionists, Staff PINs, Guest PINs
 */
class EnterpriseHierarchySeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🏨 Seeding Enterprise Hierarchy (Royal Spring Group + 2 Branches)...');

        // Ensure roles exist
        $this->call(RoleAndPermissionSeeder::class);

        DB::transaction(function () {
            // 1. Create Parent Group
            $group = HotelGroup::firstOrCreate(
                ['id' => 1],
                [
                    'name'          => 'Royal Spring Group',
                    'slug'          => 'royal-spring-group',
                    'contact_email' => 'groupadmin@royalspring.com',
                    'country'       => 'Nigeria',
                    'currency'      => 'NGN',
                    'tax_rate'      => 7.50,
                    'is_active'     => true,
                    'is_licensed'   => true,
                ]
            );

            // Group Website for Port 3005 Public Booking
            HotelGroupWebsite::updateOrCreate(
                ['hotel_group_id' => $group->id],
                [
                    'slug'            => 'royal-spring-group',
                    'title'           => 'Royal Spring Luxury Collection',
                    'description'     => 'Premier Hospitality in Lagos & Beyond',
                    'primary_color'   => '#4f46e5',
                    'secondary_color' => '#06b6d4',
                    'is_active'       => true,
                ]
            );

            // 2. Create Branch A (Ikeja Main Hotel)
            $branchA = Hotel::withoutGlobalScopes()->updateOrCreate(
                ['id' => 1],
                [
                    'name'           => 'Ikeja Main Hotel',
                    'slug'           => 'ikeja',
                    'domain'         => 'ikeja.royalspring.com',
                    'hotel_group_id' => $group->id,
                    'email'          => 'info.ikeja@royalspring.com',
                    'phone'          => '+2348000000001',
                    'address'        => 'Mobolaji Bank Anthony Way, Ikeja, Lagos',
                    'is_active'      => true,
                ]
            );

            HotelSetting::updateOrCreate(
                ['hotel_id' => $branchA->id, 'setting_key' => 'loyalty_conversion_rate'],
                ['setting_value' => '5000', 'type' => 'integer']
            );

            // Outlets for Branch A
            $outletA1 = Outlet::withoutGlobalScopes()->firstOrCreate(
                ['hotel_id' => $branchA->id, 'name' => 'Ikeja Main Restaurant'],
                ['type' => 'restaurant', 'is_active' => true]
            );
            $outletA2 = Outlet::withoutGlobalScopes()->firstOrCreate(
                ['hotel_id' => $branchA->id, 'name' => 'Ikeja Bar & Lounge'],
                ['type' => 'bar', 'is_active' => true]
            );

            // 3. Create Branch B (Victoria Island Hotel)
            $branchB = Hotel::withoutGlobalScopes()->updateOrCreate(
                ['id' => 2],
                [
                    'name'           => 'Victoria Island Hotel',
                    'slug'           => 'vi',
                    'domain'         => 'vi.royalspring.com',
                    'hotel_group_id' => $group->id,
                    'email'          => 'info.vi@royalspring.com',
                    'phone'          => '+2348000000002',
                    'address'        => 'Ahmadu Bello Way, Victoria Island, Lagos',
                    'is_active'      => true,
                ]
            );

            HotelSetting::updateOrCreate(
                ['hotel_id' => $branchB->id, 'setting_key' => 'loyalty_conversion_rate'],
                ['setting_value' => '5000', 'type' => 'integer']
            );

            // Outlets for Branch B
            $outletB1 = Outlet::withoutGlobalScopes()->firstOrCreate(
                ['hotel_id' => $branchB->id, 'name' => 'VI Oceanside Dining'],
                ['type' => 'restaurant', 'is_active' => true]
            );

            // 4. Create Roles Helper
            $superRole   = Role::where('slug', 'superadmin')->first();
            $groupRole   = Role::whereIn('slug', ['groupadmin', 'group-admin'])->first();
            $gmRole      = Role::whereIn('slug', ['generalmanager', 'hotelowner', 'manager'])->first();
            $receptRole  = Role::whereIn('slug', ['receptionist', 'reception'])->first();
            $waiterRole  = Role::whereIn('slug', ['waiter', 'waitress'])->first();
            $chefRole    = Role::whereIn('slug', ['chef', 'kitchen'])->first();

            // 5. Seed Users
            // Port 3000: Super Admin
            $superAdmin = User::withoutGlobalScopes()->updateOrCreate(
                ['email' => 'superadmin@dmtech.com'],
                [
                    'name'           => 'DM Tech Super Admin',
                    'password'       => Hash::make('SuperAdmin@2026!'),
                    'is_super_admin' => true,
                    'is_approved'    => true,
                    'hotel_id'       => $branchA->id,
                    'hotel_group_id' => $group->id,
                ]
            );
            if ($superRole) {
                $superAdmin->roles()->syncWithoutDetaching([$superRole->id => ['hotel_id' => $branchA->id]]);
            }

            // Port 3001: Group Admin
            $groupAdmin = User::withoutGlobalScopes()->updateOrCreate(
                ['email' => 'groupadmin@royalspring.com'],
                [
                    'name'           => 'Royal Spring Group Admin',
                    'password'       => Hash::make('GroupAdmin@2026!'),
                    'is_super_admin' => false,
                    'is_approved'    => true,
                    'hotel_id'       => null,
                    'hotel_group_id' => $group->id,
                ]
            );
            if ($groupRole) {
                $groupAdmin->roles()->syncWithoutDetaching([$groupRole->id => ['hotel_id' => null]]);
            }

            // Port 3002: Branch A GM & Reception
            $gmA = User::withoutGlobalScopes()->updateOrCreate(
                ['email' => 'gm.ikeja@royalspring.com'],
                [
                    'name'           => 'Ikeja General Manager',
                    'password'       => Hash::make('IkejaManager@2026!'),
                    'is_super_admin' => false,
                    'is_approved'    => true,
                    'hotel_id'       => $branchA->id,
                    'hotel_group_id' => $group->id,
                ]
            );
            if ($gmRole) {
                $gmA->roles()->syncWithoutDetaching([$gmRole->id => ['hotel_id' => $branchA->id]]);
            }

            $receptA = User::withoutGlobalScopes()->updateOrCreate(
                ['email' => 'reception.ikeja@royalspring.com'],
                [
                    'name'           => 'Ikeja Front Desk Receptionist',
                    'password'       => Hash::make('IkejaDesk@2026!'),
                    'is_super_admin' => false,
                    'is_approved'    => true,
                    'hotel_id'       => $branchA->id,
                    'hotel_group_id' => $group->id,
                ]
            );
            if ($receptRole) {
                $receptA->roles()->syncWithoutDetaching([$receptRole->id => ['hotel_id' => $branchA->id]]);
            }

            // Port 3002: Branch B GM & Reception
            $gmB = User::withoutGlobalScopes()->updateOrCreate(
                ['email' => 'gm.vi@royalspring.com'],
                [
                    'name'           => 'VI General Manager',
                    'password'       => Hash::make('VIManager@2026!'),
                    'is_super_admin' => false,
                    'is_approved'    => true,
                    'hotel_id'       => $branchB->id,
                    'hotel_group_id' => $group->id,
                ]
            );
            if ($gmRole) {
                $gmB->roles()->syncWithoutDetaching([$gmRole->id => ['hotel_id' => $branchB->id]]);
            }

            $receptB = User::withoutGlobalScopes()->updateOrCreate(
                ['email' => 'reception.vi@royalspring.com'],
                [
                    'name'           => 'VI Front Desk Receptionist',
                    'password'       => Hash::make('VIDesk@2026!'),
                    'is_super_admin' => false,
                    'is_approved'    => true,
                    'hotel_id'       => $branchB->id,
                    'hotel_group_id' => $group->id,
                ]
            );
            if ($receptRole) {
                $receptB->roles()->syncWithoutDetaching([$receptRole->id => ['hotel_id' => $branchB->id]]);
            }

            // Port 3003: Branch A Waiter & Chef (PINs: 1111 / 2222)
            $waiterA = User::withoutGlobalScopes()->updateOrCreate(
                ['email' => 'waiter.ikeja@royalspring.com'],
                [
                    'name'           => 'Ikeja Staff Waiter',
                    'password'       => Hash::make('StaffPass@2026!'),
                    'pin_code'       => '1111',
                    'is_super_admin' => false,
                    'is_approved'    => true,
                    'is_on_duty'     => true,
                    'hotel_id'       => $branchA->id,
                    'outlet_id'      => $outletA1->id,
                    'hotel_group_id' => $group->id,
                    'password_changed_at' => now(),
                ]
            );
            if ($waiterRole) {
                $waiterA->roles()->syncWithoutDetaching([$waiterRole->id => ['hotel_id' => $branchA->id]]);
            }

            $chefA = User::withoutGlobalScopes()->updateOrCreate(
                ['email' => 'chef.ikeja@royalspring.com'],
                [
                    'name'           => 'Ikeja Head Chef',
                    'password'       => Hash::make('StaffPass@2026!'),
                    'pin_code'       => '2222',
                    'is_super_admin' => false,
                    'is_approved'    => true,
                    'is_on_duty'     => true,
                    'hotel_id'       => $branchA->id,
                    'outlet_id'      => $outletA1->id,
                    'hotel_group_id' => $group->id,
                    'password_changed_at' => now(),
                ]
            );
            if ($chefRole) {
                $chefA->roles()->syncWithoutDetaching([$chefRole->id => ['hotel_id' => $branchA->id]]);
            }

            // Port 3003: Branch B Waiter & Chef (PINs: 3333 / 4444)
            $waiterB = User::withoutGlobalScopes()->updateOrCreate(
                ['email' => 'waiter.vi@royalspring.com'],
                [
                    'name'           => 'VI Staff Waiter',
                    'password'       => Hash::make('StaffPass@2026!'),
                    'pin_code'       => '3333',
                    'is_super_admin' => false,
                    'is_approved'    => true,
                    'is_on_duty'     => true,
                    'hotel_id'       => $branchB->id,
                    'outlet_id'      => $outletB1->id,
                    'hotel_group_id' => $group->id,
                    'password_changed_at' => now(),
                ]
            );
            if ($waiterRole) {
                $waiterB->roles()->syncWithoutDetaching([$waiterRole->id => ['hotel_id' => $branchB->id]]);
            }

            $chefB = User::withoutGlobalScopes()->updateOrCreate(
                ['email' => 'chef.vi@royalspring.com'],
                [
                    'name'           => 'VI Head Chef',
                    'password'       => Hash::make('StaffPass@2026!'),
                    'pin_code'       => '4444',
                    'is_super_admin' => false,
                    'is_approved'    => true,
                    'is_on_duty'     => true,
                    'hotel_id'       => $branchB->id,
                    'outlet_id'      => $outletB1->id,
                    'hotel_group_id' => $group->id,
                    'password_changed_at' => now(),
                ]
            );
            if ($chefRole) {
                $chefB->roles()->syncWithoutDetaching([$chefRole->id => ['hotel_id' => $branchB->id]]);
            }

            // 6. Seed Rooms & Guests for Port 3004 In-Hotel Access
            // Branch A: Room 101 (PIN: 1010)
            $roomTypeA = RoomType::withoutGlobalScopes()->firstOrCreate(
                ['hotel_id' => $branchA->id, 'name' => 'Executive Deluxe Suite'],
                ['base_price' => 85000, 'capacity' => 2, 'description' => 'Luxury suite with city view', 'is_public' => true]
            );
            $room101 = Room::withoutGlobalScopes()->updateOrCreate(
                ['hotel_id' => $branchA->id, 'room_number' => '101'],
                ['room_type_id' => $roomTypeA->id, 'status' => 'occupied', 'floor' => 1]
            );
            $guestA = Guest::withoutGlobalScopes()->firstOrCreate(
                ['hotel_id' => $branchA->id, 'email' => 'guest101@royalspring.com'],
                [
                    'first_name' => 'Alexander',
                    'last_name'  => 'Wright',
                    'phone'      => '+2348011111111',
                    'pin_code'   => '1010',
                ]
            );
            $resA = Reservation::withoutGlobalScopes()->updateOrCreate(
                ['hotel_id' => $branchA->id, 'reservation_number' => 'RES-IKEJA-101'],
                [
                    'guest_id'        => $guestA->id,
                    'check_in_date'   => now()->subDay(),
                    'check_out_date'  => now()->addDays(2),
                    'total_amount'    => 170000,
                    'status'          => 'checked_in',
                ]
            );
            $resA->rooms()->syncWithoutDetaching([$room101->id => ['rate' => 85000]]);

            \App\Models\GuestPortalSession::updateOrCreate(
                ['hotel_id' => $branchA->id, 'room_id' => $room101->id],
                [
                    'guest_id'      => $guestA->id,
                    'reservation_id'=> $resA->id,
                    'session_token' => 'guest-token-ikeja-101',
                    'pin_code'      => '1010',
                    'expires_at'    => now()->addDays(2),
                    'status'        => 'active',
                ]
            );

            // Branch B: Room 201 (PIN: 2020)
            $roomTypeB = RoomType::withoutGlobalScopes()->firstOrCreate(
                ['hotel_id' => $branchB->id, 'name' => 'Ocean View Presidential'],
                ['base_price' => 120000, 'capacity' => 4, 'description' => 'Oceanfront penthouse', 'is_public' => true]
            );
            $room201 = Room::withoutGlobalScopes()->updateOrCreate(
                ['hotel_id' => $branchB->id, 'room_number' => '201'],
                ['room_type_id' => $roomTypeB->id, 'status' => 'occupied', 'floor' => 2]
            );
            $guestB = Guest::withoutGlobalScopes()->firstOrCreate(
                ['hotel_id' => $branchB->id, 'email' => 'guest201@royalspring.com'],
                [
                    'first_name' => 'Victoria',
                    'last_name'  => 'Sterling',
                    'phone'      => '+2348022222222',
                    'pin_code'   => '2020',
                ]
            );
            $resB = Reservation::withoutGlobalScopes()->updateOrCreate(
                ['hotel_id' => $branchB->id, 'reservation_number' => 'RES-VI-201'],
                [
                    'guest_id'        => $guestB->id,
                    'check_in_date'   => now()->subDay(),
                    'check_out_date'  => now()->addDays(3),
                    'total_amount'    => 360000,
                    'status'          => 'checked_in',
                ]
            );
            $resB->rooms()->syncWithoutDetaching([$room201->id => ['rate' => 120000]]);

            \App\Models\GuestPortalSession::updateOrCreate(
                ['hotel_id' => $branchB->id, 'room_id' => $room201->id],
                [
                    'guest_id'      => $guestB->id,
                    'reservation_id'=> $resB->id,
                    'session_token' => 'guest-token-vi-201',
                    'pin_code'      => '2020',
                    'expires_at'    => now()->addDays(3),
                    'status'        => 'active',
                ]
            );
        });

        $this->command->info('✅ Enterprise Hierarchy seeding completed successfully!');
    }
}
