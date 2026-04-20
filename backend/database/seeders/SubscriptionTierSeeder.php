<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionTier;

class SubscriptionTierSeeder extends Seeder
{
    public function run()
    {
        $tiers = [
            [
                'name' => 'Retail',
                'price' => 50.00,
                'room_limit' => 0,
                'features' => ['POS'],
            ],
            [
                'name' => 'Hospitality Lite',
                'price' => 100.00,
                'room_limit' => 0,
                'features' => ['POS', 'KDS'],
            ],
            [
                'name' => 'Boutique',
                'price' => 200.00,
                'room_limit' => 15,
                'features' => ['POS', 'KDS', 'PMS'],
            ],
            [
                'name' => 'Business',
                'price' => 350.00,
                'room_limit' => 40,
                'features' => ['POS', 'KDS', 'PMS'],
            ],
            [
                'name' => 'Enterprise',
                'price' => 600.00,
                'room_limit' => 999999, // practically unlimited
                'features' => ['POS', 'KDS', 'PMS'],
            ],
        ];

        foreach ($tiers as $tier) {
            SubscriptionTier::updateOrCreate(['name' => $tier['name']], $tier);
        }
    }
}
