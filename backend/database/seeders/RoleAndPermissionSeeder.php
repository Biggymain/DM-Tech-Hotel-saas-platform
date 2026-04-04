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
            'pms.rooms.view' => 'View PMS Rooms',
            'pms.rooms.manage' => 'Manage PMS Rooms',
            'pms.reservations.view' => 'View Reservations',
            'pms.reservations.manage' => 'Manage Reservations',
            'pms.checkin' => 'Process Guest Check-in',
            'pms.checkout' => 'Process Guest Check-out',
            'pms.housekeeping.manage' => 'Manage Housekeeping Status',
            'reservations.manage' => 'Reservations management',
            'restaurant.orders.manage' => 'Restaurant orders',
            'inventory.manage' => 'Inventory management',
            'inventory.view' => 'Inventory viewing',
            'finance.manage' => 'Finance management',
            'billing.view' => 'Billing viewer',
            'billing.manage' => 'Billing management',
            'payments.process' => 'Payment processing',
            'payments.refund' => 'Payment refunding',
            'reports.view' => 'Business Intelligence view',
            'reports.export' => 'Business Intelligence export',
            'notifications.view' => 'Notification view',
            'notifications.manage' => 'Notification management',
            'system.activity.view' => 'System activity view',
            'system.audit.view' => 'System audit view',
            'guest.requests.view' => 'View Guest Requests',
            'guest.requests.manage' => 'Manage Guest Requests',
            'users.view' => 'View Staff Users',
            'users.create' => 'Create Staff Users',
            'users.update' => 'Update Staff Users',
            'users.delete' => 'Delete Staff Users',
            'pricing.rate_plans.view' => 'View Rate Plans',
            'pricing.rate_plans.manage' => 'Manage Rate Plans',
            'orders.view' => 'View POS Orders',
            'orders.create' => 'Create POS Orders',
            'orders.update' => 'Update POS Orders',
            'orders.delete' => 'Delete POS Orders',
            'kds.view' => 'View Kitchen Display',
            'pos.manage' => 'Manage POS Operations',
            'leisure.manage' => 'Manage Leisure Hub',
        ];

        // Seed permissions
        $dbPermissions = [];
        foreach ($permissions as $slug => $name) {
            $dbPermissions[$slug] = \App\Models\Permission::create([
                'name' => $name,
                'slug' => $slug,
                'module' => explode('.', $slug)[0] ?? 'general',
            ]);
        }

        $roles = [
            'SuperAdmin' => [], // Has everything bypass
            'HotelOwner' => array_keys($permissions),
            'GroupAdmin' => array_keys($permissions),
            'GeneralManager' => ['rooms.manage', 'pms.rooms.view', 'pms.rooms.manage', 'pms.reservations.view', 'pms.reservations.manage', 'pms.checkin', 'pms.checkout', 'pms.housekeeping.manage', 'reservations.manage', 'restaurant.orders.manage', 'orders.view', 'orders.create', 'orders.update', 'orders.delete', 'kds.view', 'inventory.manage', 'inventory.view', 'billing.manage', 'billing.view', 'payments.process', 'payments.refund', 'reports.view', 'notifications.view', 'notifications.manage', 'system.activity.view', 'system.audit.view', 'users.view', 'users.create', 'users.update', 'users.delete', 'pos.manage', 'leisure.manage'],
            'Manager' => ['rooms.manage', 'pms.rooms.view', 'pms.rooms.manage', 'pms.reservations.view', 'pms.reservations.manage', 'pms.checkin', 'pms.checkout', 'pms.housekeeping.manage', 'reservations.manage', 'restaurant.orders.manage', 'orders.view', 'orders.create', 'orders.update', 'orders.delete', 'kds.view', 'inventory.manage', 'inventory.view', 'billing.manage', 'billing.view', 'payments.process', 'payments.refund', 'reports.view', 'notifications.view', 'notifications.manage', 'system.activity.view', 'system.audit.view', 'users.view', 'users.create', 'users.update', 'users.delete'],
            'ITSpecialist' => ['system.activity.view', 'system.audit.view', 'notifications.manage', 'users.view', 'users.create', 'users.update', 'users.delete', 'hotel.manage'],
            'Reception' => ['rooms.manage', 'pms.rooms.view', 'pms.rooms.manage', 'pms.reservations.view', 'pms.reservations.manage', 'pms.checkin', 'pms.checkout', 'reservations.manage', 'billing.view', 'payments.process', 'notifications.view', 'users.view', 'users.create'],
            'Housekeeping' => ['pms.rooms.view', 'pms.housekeeping.manage', 'notifications.view'],
            'Cashier' => ['billing.manage', 'billing.view', 'payments.process', 'payments.refund', 'notifications.view', 'restaurant.orders.manage', 'orders.view', 'orders.update'],
            'Kitchen' => ['restaurant.orders.manage', 'orders.view', 'orders.update', 'kds.view', 'inventory.manage', 'inventory.view', 'notifications.view'],
            'Waitress' => ['restaurant.orders.manage', 'orders.view', 'orders.create', 'orders.update', 'inventory.view', 'notifications.view', 'pos.manage'],
            'OutletManager' => ['restaurant.orders.manage', 'orders.view', 'orders.create', 'orders.update', 'orders.delete', 'inventory.manage', 'inventory.view', 'reports.view', 'reports.export', 'users.view', 'users.create', 'notifications.view', 'pos.manage', 'leisure.manage'],
            'Accountant' => ['finance.manage', 'billing.manage', 'billing.view', 'payments.process', 'payments.refund', 'reports.view', 'reports.export', 'notifications.view', 'system.activity.view', 'system.audit.view'],
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
