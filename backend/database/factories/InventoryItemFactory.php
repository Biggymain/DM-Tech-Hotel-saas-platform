<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => \App\Models\Hotel::factory(),
            'outlet_id' => \App\Models\Outlet::factory(),
            'name' => $this->faker->word . ' Drink',
            'sku' => $this->faker->unique()->bothify('SKU-####'),
            'category' => 'beverage',
            'unit_of_measurement' => 'bottle',
            'current_stock' => 100,
            'status' => 'active',
        ];
    }
}
