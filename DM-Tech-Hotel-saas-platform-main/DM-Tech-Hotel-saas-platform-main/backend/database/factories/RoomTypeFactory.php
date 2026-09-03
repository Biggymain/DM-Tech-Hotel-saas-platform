<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RoomType>
 */
class RoomTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->word() . ' Room';
        return [
            'hotel_id' => \App\Models\Hotel::factory(),
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name) . '-' . uniqid(),
            'base_price' => $this->faker->randomFloat(2, 50, 500),
            'capacity' => $this->faker->numberBetween(1, 4),
        ];
    }
}
