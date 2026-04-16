<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelGroup;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * GroupRegistrationService
 *
 * Creates a new HotelGroup (Organization) with its first Branch Hotel
 * and a GROUP_ADMIN user. This service intentionally has NO tenant scoping —
 * it operates at the "central" SaaS layer, above any individual hotel tenant.
 */
class GroupRegistrationService
{
    public function registerGroup(array $data): array
    {
        return DB::transaction(function () use ($data) {

            // 1. Create the HotelGroup (Organization)
            $group = HotelGroup::create([
                'name'          => $data['group_name'],
                'slug'          => Str::slug($data['group_name']) . '-' . Str::random(4),
                'contact_email' => $data['email'],
                'currency'      => $data['currency'] ?? 'USD',
                'tax_rate'      => $data['tax_rate'] ?? 0,
                'is_active'     => true,
            ]);

            // 2. Create the first Branch Hotel, inheriting group settings
            $branch = Hotel::create([
                'hotel_group_id' => $group->getAttribute('id'),
                'name'           => $data['hotel_name'],
                'domain'         => Str::slug($data['hotel_name']) . '-' . Str::random(4),
                'email'          => $data['email'],
                'is_active'      => true,
            ]);

            // 2.1 Sync with Supabase Licensing Hub
            $this->syncBranchToSupabase($branch, $data['tier'] ?? 'basic', $group->getAttribute('id'));

            // 3. Create the GROUP_ADMIN user (belongs to group, NOT a single hotel)
            $groupAdmin = User::create([
                'name'           => $data['owner_name'],
                'email'          => $data['email'],
                'password'       => $data['password'],
                'hotel_group_id' => $group->getAttribute('id'),
                'hotel_id'       => null,     // GROUP_ADMIN is cross-branch
                'is_super_admin' => false,
            ]);

            // 4. Assign groupadmin role (Ensure it exists as a system role)
            $groupAdminRole = Role::withoutGlobalScopes()->firstOrCreate(
                ['slug' => 'groupadmin'],
                ['name' => 'Group Admin', 'is_system_role' => true]
            );
            // Grant all permissions to group admin
            $allPermissions = \App\Models\Permission::pluck('id')->toArray();
            $groupAdminRole->permissions()->sync($allPermissions);

            $groupAdmin->roles()->attach($groupAdminRole->id, ['hotel_id' => null]);

            // 5. Seed default outlets on the first branch
            $defaultOutlets = [
                ['name' => 'Main Restaurant', 'type' => 'restaurant'],
                ['name' => 'Room Service',    'type' => 'room_service'],
                ['name' => 'Main Bar',        'type' => 'bar'],
            ];
            foreach ($defaultOutlets as $outletData) {
                Outlet::create([
                    'hotel_id'  => $branch->id,
                    'name'      => $outletData['name'],
                    'type'      => $outletData['type'],
                    'is_active' => true,
                ]);
            }

            // 6. Issue auth token
            $token = $groupAdmin->createToken('group_admin_token')->plainTextToken;

            return [
                'group'  => $group,
                'branch' => $branch,
                'user'   => $groupAdmin,
                'token'  => $token,
            ];
        });
    }

    /**
     * Create a new branch within an existing group, inheriting currency/tax settings.
     */
    public function createBranch(HotelGroup $group, array $data): Hotel
    {
        return DB::transaction(function () use ($group, $data) {
            $branch = Hotel::create([
                'hotel_group_id' => $group->id,
                'name'           => $data['name'],
                'domain'         => Str::slug($data['name']) . '-' . Str::random(4),
                'email'          => $data['email'] ?? $group->contact_email,
                'phone'          => $data['phone'] ?? null,
                'address'        => $data['address'] ?? null,
                'is_active'      => true,
            ]);

            // Sync with Supabase Licensing Hub
            $this->syncBranchToSupabase($branch, $data['tier'] ?? 'basic', $group->id);

            // Seed default outlets on the new branch too
            $defaultOutlets = [
                ['name' => 'Main Restaurant', 'type' => 'restaurant'],
                ['name' => 'Room Service',    'type' => 'room_service'],
                ['name' => 'Main Bar',        'type' => 'bar'],
            ];
            foreach ($defaultOutlets as $outletData) {
                Outlet::create([
                    'hotel_id'  => $branch->getAttribute('id'),
                    'name'      => $outletData['name'],
                    'type'      => $outletData['type'],
                    'is_active' => true,
                ]);
            }

            return $branch;
        });
    }

    /**
     * Internal: Syncs a local branch model to the Supabase Licensing system.
     */
    private function syncBranchToSupabase(Hotel $branch, string $tier, $groupId): void
    {
        $expiryDays = match($tier) {
            'basic' => 30,
            'standard' => 90,
            'premium' => 365,
            'enterprise' => 730,
            default => 30
        };

        DB::connection('supabase')->table('branches')->insert([
            'id' => $branch->getAttribute('id'), // Robust UUID access
            'group_id' => $groupId,
            'expires_at' => now()->addDays($expiryDays),
            'manager_email' => $branch->getAttribute('email'), // Mapped for licensing alerts
            'owner_email' => $branch->getAttribute('email'),   // Primary fallback
            'created_at' => now(),
        ]);
    }
}
