<?php

namespace Database\Factories;

use App\Models\LeisureBundle;
use App\Models\Hotel;
use App\Models\MenuItem;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeisureBundle>
 */
class LeisureBundleFactory extends Factory
{
    protected $model = LeisureBundle::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'menu_item_id' => MenuItem::factory(),
            'inventory_item_id' => InventoryItem::factory(),
            'quantity' => $this->faker->numberBetween(1, 10),
        ];
    }
}
