<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Hotel;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');

        if (!$email || !$password) {
            $this->command->error('SUPER_ADMIN_EMAIL or SUPER_ADMIN_PASSWORD not set in .env');
            return;
        }

        // We need a hotel to attach the user to (as per the User model requirements)
        // Usually, the first hotel created by SetupSeeder
        $hotel = Hotel::first();

        if (!$hotel) {
            $this->command->error('No hotel found. Please run SetupSeeder first.');
            return;
        }

        $superAdmin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Platform Super Admin',
                'password' => Hash::make($password),
                'is_super_admin' => true,
                'hotel_id' => $hotel->id,
            ]
        );

        // Attach Super Admin role if it exists
        $superRole = Role::where('slug', 'superadmin')->first();
        if ($superRole) {
            $superAdmin->roles()->syncWithoutDetaching([
                $superRole->id => ['hotel_id' => $hotel->id]
            ]);
        }

        $this->command->info("✅ Super Admin created/updated: {$email}");
    }
}
