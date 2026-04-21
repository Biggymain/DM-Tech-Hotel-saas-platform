<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'invoice_id' => Invoice::factory(),
            'payment_method_id' => PaymentMethod::create(['name' => 'Cash', 'hotel_id' => 1])->id, // Simple link
            'type' => 'payment',
            'amount' => 1000,
            'status' => 'completed',
            'processed_by_id' => User::factory(),
        ];
    }
}
