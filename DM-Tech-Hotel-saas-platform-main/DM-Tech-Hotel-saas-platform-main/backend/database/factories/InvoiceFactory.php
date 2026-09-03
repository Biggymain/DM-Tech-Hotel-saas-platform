<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'order_id' => Order::factory(),
            'invoice_number' => 'INV-' . $this->faker->unique()->randomNumber(8),
            'sequence_number' => $this->faker->unique()->randomNumber(5),
            'total_amount' => 1000,
            'amount_paid' => 0,
            'status' => 'pending',
            'currency_code' => 'NGN',
        ];
    }
}
