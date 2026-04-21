<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'name' => $this->faker->unique()->word,
            'is_active' => true,
            'requires_reference' => false,
        ];
    }
}
