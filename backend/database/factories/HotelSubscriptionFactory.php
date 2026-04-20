<?php

namespace Database\Factories;

use App\Models\HotelSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class HotelSubscriptionFactory extends Factory
{
    protected $model = HotelSubscription::class;

    public function definition(): array
    {
        return [
            'hotel_id' => \App\Models\Hotel::factory(),
            'plan_id' => \App\Models\SubscriptionPlan::factory(),
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ];
    }
}
