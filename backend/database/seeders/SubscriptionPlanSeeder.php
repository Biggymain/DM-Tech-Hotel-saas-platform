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
                'name' => 'Starter',
                'slug' => 'starter',
                'price' => 49.00,
                'billing_cycle' => 'monthly',
                'max_rooms' => 20,
                'max_staff' => 5,
                'description' => 'Perfect for small boutiques and guesthouses.',
                'features' => ['Reservations', 'Basic POS', 'Email Support'],
                'is_active' => true,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'price' => 149.00,
                'billing_cycle' => 'monthly',
                'max_rooms' => 100,
                'max_staff' => 20,
                'description' => 'Ideal for growing mid-sized hotels.',
                'features' => ['All Starter Features', 'Advanced Analytics', 'Guest Portal', 'Channel Manager Integration'],
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price' => 499.00,
                'billing_cycle' => 'monthly',
                'max_rooms' => 1000,
                'max_staff' => 100,
                'description' => 'For large luxury hotels and multi-property groups.',
                'features' => ['All Pro Features', 'Multi-property Support', 'Custom Integrations', '24/7 Priority Support'],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
