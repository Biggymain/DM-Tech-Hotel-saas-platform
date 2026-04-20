<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'name'          => 'Pro Plan',
            'slug'          => 'hotel_pro',
            'description'   => 'Full-featured plan for hotel operations.',
            'features'      => ['pms', 'inventory', 'analytics', 'rooms.create'],
            'price'         => 50000,
            'billing_cycle' => 'monthly',
            'max_rooms'     => null,  // null = unlimited
            'max_staff'     => null,  // null = unlimited
            'is_active'     => true,
        ];
    }
}
