<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;

class OutletFactory extends Factory
{
    protected $model = Outlet::class;

    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'name' => $this->faker->company() . ' Outlet',
            'type' => $this->faker->randomElement(['restaurant', 'bar', 'cafe']),
            'is_active' => true,
        ];
    }
}
