<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderItem>
 */
class OrderItemFactory extends Factory
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
            'order_id' => \App\Models\Order::factory(),
            'menu_item_id' => \App\Models\MenuItem::factory(),
            'quantity' => 1,
            'price' => $this->faker->randomFloat(2, 50, 500),
            'subtotal' => function (array $attributes) {
                return $attributes['price'] * $attributes['quantity'];
            },
            'status' => 'pending',
        ];
    }
}
