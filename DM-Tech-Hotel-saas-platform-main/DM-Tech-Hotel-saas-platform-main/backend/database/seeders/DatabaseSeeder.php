<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * For a fresh setup, run: php artisan migrate:fresh --seed
     * Or run just the setup seeder: php artisan db:seed --class=SetupSeeder
     */
    public function run(): void
    {
        $this->call([
            SetupSeeder::class,
            SubscriptionPlanSeeder::class,
            OtaChannelSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}
