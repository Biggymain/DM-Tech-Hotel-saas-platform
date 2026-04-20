<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Tier 1 (Retail)',
                'slug' => 'tier-1-retail',
                'price' => 49.00,
                'billing_cycle' => 'monthly',
                'max_rooms' => 0,
                'max_staff' => 5,
                'description' => 'Retail/Supermarket POS only.',
                'features' => ['pos'],
                'is_active' => true,
            ],
            [
                'name' => 'Tier 2 (Hospitality)',
                'slug' => 'tier-2-hospitality',
                'price' => 99.00,
                'billing_cycle' => 'monthly',
                'max_rooms' => 0,
                'max_staff' => 10,
                'description' => 'POS + Kitchen Display System.',
                'features' => ['pos', 'kds'],
                'is_active' => true,
            ],
            [
                'name' => 'Tier 3 (Boutique)',
                'slug' => 'tier-3-boutique',
                'price' => 199.00,
                'billing_cycle' => 'monthly',
                'max_rooms' => 15,
                'max_staff' => 20,
                'description' => 'Full PMS + POS + KDS for small boutique hotels.',
                'features' => ['pos', 'kds', 'pms'],
                'is_active' => true,
            ],
            [
                'name' => 'Tier 4 (Business)',
                'slug' => 'tier-4-business',
                'price' => 299.00,
                'billing_cycle' => 'monthly',
                'max_rooms' => 40,
                'max_staff' => 50,
                'description' => 'Extended PMS + POS + KDS for mid-sized hotels.',
                'features' => ['pos', 'kds', 'pms'],
                'is_active' => true,
            ],
            [
                'name' => 'Tier 5 (Enterprise)',
                'slug' => 'tier-5-enterprise',
                'price' => 499.00,
                'billing_cycle' => 'monthly',
                'max_rooms' => null, // Unlimited
                'max_staff' => null,
                'description' => 'Unlimited modules and rooms for enterprise groups.',
                'features' => ['pos', 'kds', 'pms', 'analytics', 'multi-branch'],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
